<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemOperator;
use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\Asset\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @package storefront
 */
class ThemeCompiler implements ThemeCompilerInterface
{
    /**
     * @internal
     *
     * @param Package[] $packages
     */
    public function __construct(
        private FilesystemOperator $filesystem,
        private FilesystemOperator $tempFilesystem,
        private ThemeFileResolver $themeFileResolver,
        private bool $debug,
        private EventDispatcherInterface $eventDispatcher,
        private ThemeFileImporterInterface $themeFileImporter,
        private iterable $packages,
        private CacheInvalidator $logger,
        private AbstractThemePathBuilder $themePathBuilder,
        private string $projectDir,
        private AbstractScssCompiler $scssCompiler,
        private MessageBusInterface $messageBus
    ) {
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets,
        Context $context
    ): void {
        $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configurationCollection, false);

        $styleFiles = $resolvedFiles[ThemeFileResolver::STYLE_FILES];

        $concatenatedStyles = $this->concatenateStyles(
            $styleFiles,
            $themeConfig,
            $salesChannelId
        );

        $compiled = $this->compileStyles(
            $concatenatedStyles,
            $themeConfig,
            $styleFiles->getResolveMappings(),
            $salesChannelId,
            $context
        );

        $concatenatedScripts = $this->getConcatenatedScripts($resolvedFiles[ThemeFileResolver::SCRIPT_FILES], $themeConfig, $salesChannelId);

        $newThemeHash = Uuid::randomHex();
        $themePrefix = $this->themePathBuilder->generateNewPath($salesChannelId, $themeId, $newThemeHash);
        $oldThemePrefix = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);

        try {
            $this->writeCompiledFiles($themePrefix, $compiled, $concatenatedScripts, $withAssets, $themeConfig, $configurationCollection);
        } catch (\Throwable $e) {
            // delete folder in case of error and rethrow exception
            if ($themePrefix !== $oldThemePrefix) {
                $this->filesystem->deleteDirectory($themePrefix);
            }

            throw $e;
        }

        $this->themePathBuilder->saveSeed($salesChannelId, $themeId, $newThemeHash);

        if ($themePrefix !== $oldThemePrefix) {
            // only delete the old directory if the `themePathBuilder` actually returned a new path and supports seeding
            // also delete with a delay of one our, so that the old theme is still available for a while in case some CDN delivers stale content
            $this->messageBus->dispatch(
                new Envelope(
                    new DeleteThemeFilesMessage($oldThemePrefix, $salesChannelId, $themeId),
                    // one hour in milliseconds
                    [new DelayStamp(3600 * 1000)]
                )
            );
        }

        // Reset cache buster state for improving performance in getMetadata
        $this->logger->invalidate(['theme-metaData'], true);
    }

    /**
     * @param array<string, string> $resolveMappings
     */
    public function getResolveImportPathsCallback(array $resolveMappings): \Closure
    {
        return function ($originalPath) use ($resolveMappings) {
            foreach ($resolveMappings as $resolve => $resolvePath) {
                $resolve = '~' . $resolve;
                if (mb_strpos($originalPath, $resolve) === 0) {
                    $dirname = $resolvePath . \dirname(mb_substr($originalPath, mb_strlen($resolve)));

                    $filename = basename($originalPath);
                    $extension = $this->getImportFileExtension(pathinfo($filename, \PATHINFO_EXTENSION));
                    $path = $dirname . \DIRECTORY_SEPARATOR . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }

                    $path = $dirname . \DIRECTORY_SEPARATOR . '_' . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }

            return null;
        };
    }

    private function copyAssets(
        StorefrontPluginConfiguration $configuration,
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $outputPath
    ): void {
        if (!$configuration->getAssetPaths()) {
            return;
        }

        foreach ($configuration->getAssetPaths() as $asset) {
            if (mb_strpos($asset, '@') === 0) {
                $name = mb_substr($asset, 1);
                $config = $configurationCollection->getByTechnicalName($name);
                if (!$config) {
                    throw new InvalidThemeException($name);
                }

                $this->copyAssets($config, $configurationCollection, $outputPath);

                continue;
            }

            if ($asset[0] !== '/' && file_exists($this->projectDir . '/' . $asset)) {
                $asset = $this->projectDir . '/' . $asset;
            }

            $assets = $this->themeFileImporter->getCopyBatchInputsForAssets($asset, $outputPath, $configuration);

            // method copyBatch is provided by copyBatch filesystem plugin
            CopyBatch::copy($this->filesystem, ...$assets);
        }
    }

    /**
     * @param array<string, string> $resolveMappings
     */
    private function compileStyles(
        string $concatenatedStyles,
        StorefrontPluginConfiguration $configuration,
        array $resolveMappings,
        string $salesChannelId,
        Context $context
    ): string {
        $variables = $this->dumpVariables($configuration->getThemeConfig() ?? [], $salesChannelId, $context);
        $features = $this->getFeatureConfigScssMap();

        $resolveImportPath = $this->getResolveImportPathsCallback($resolveMappings);

        $importPaths = [];

        $cwd = \getcwd();
        if ($cwd !== false) {
            $importPaths[] = $cwd;
        }

        $importPaths[] = $resolveImportPath;

        $compilerConfig = new CompilerConfiguration(
            [
                'importPaths' => $importPaths,
                'outputStyle' => $this->debug ? OutputStyle::EXPANDED : OutputStyle::COMPRESSED,
            ]
        );

        try {
            $cssOutput = $this->scssCompiler->compileString(
                $compilerConfig,
                $features . $variables . $concatenatedStyles
            );
        } catch (\Throwable $exception) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                $exception->getMessage()
            );
        }
        $autoPreFixer = new Autoprefixer($cssOutput);
        /** @var string|false $compiled */
        $compiled = $autoPreFixer->compile($this->debug);
        if ($compiled === false) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                'CSS parser not initialized'
            );
        }

        return $compiled;
    }

    private function getImportFileExtension(string $extension): string
    {
        // If the import has no extension, it must be a SCSS module.
        if ($extension === '') {
            return '.scss';
        }

        // If the import has a .min extension, we assume it must be a compiled CSS file.
        if ($extension === 'min') {
            return '.css';
        }

        // If it has any other extension, we don't assume a specific extension.
        return '';
    }

    /**
     * Converts the feature config array to a SCSS map syntax.
     * This allows reading of the feature flag config inside SCSS via `map.get` function.
     *
     * Output example:
     * $sw-features: ("FEATURE_NEXT_1234": false, "FEATURE_NEXT_1235": true);
     *
     * @see https://sass-lang.com/documentation/values/maps
     */
    private function getFeatureConfigScssMap(): string
    {
        $allFeatures = Feature::getAll();

        $featuresScss = implode(',', array_map(function ($value, $key) {
            return sprintf('"%s": %s', $key, json_encode($value));
        }, $allFeatures, array_keys($allFeatures)));

        return sprintf('$sw-features: (%s);', $featuresScss);
    }

    /**
     * @param array<string, string> $variables
     *
     * @return array<string>
     */
    private function formatVariables(array $variables): array
    {
        return array_map(function ($value, $key) {
            return sprintf('$%s: %s;', $key, (!empty($value) ? $value : 0));
        }, $variables, array_keys($variables));
    }

    /**
     * @param array{fields?: array{value: null|string|array<mixed>, scss?: bool, type: string}[]} $config
     */
    private function dumpVariables(array $config, string $salesChannelId, Context $context): string
    {
        $variables = [];
        foreach ($config['fields'] ?? [] as $key => $data) {
            if (!\is_array($data) || !$this->isDumpable($data)) {
                continue;
            }

            if (\in_array($data['type'], ['media', 'textarea'], true) && \is_string($data['value'])) {
                $variables[$key] = '\'' . $data['value'] . '\'';
            } elseif ($data['type'] === 'switch' || $data['type'] === 'checkbox') {
                $variables[$key] = (int) ($data['value']);
            } else {
                $variables[$key] = $data['value'];
            }
        }

        foreach ($this->packages as $key => $package) {
            $variables[sprintf('sw-asset-%s-url', $key)] = sprintf('\'%s\'', $package->getUrl(''));
        }

        $themeVariablesEvent = new ThemeCompilerEnrichScssVariablesEvent(
            $variables,
            $salesChannelId,
            $context
        );

        $this->eventDispatcher->dispatch($themeVariablesEvent);

        $dump = str_replace(
            ['#class#', '#variables#'],
            [self::class, implode(\PHP_EOL, $this->formatVariables($themeVariablesEvent->getVariables()))],
            $this->getVariableDumpTemplate()
        );

        $this->tempFilesystem->write('theme-variables.scss', $dump);

        return $dump;
    }

    /**
     * @param array{value: string|array<mixed>|null, scss?: bool, type: string} $data
     */
    private function isDumpable(array $data): bool
    {
        if (!isset($data['value'])) {
            return false;
        }

        // Do not include fields which have the scss option set to false
        if (\array_key_exists('scss', $data) && $data['scss'] === false) {
            return false;
        }

        // Do not include fields which haa an array as value
        if (\is_array($data['value'])) {
            return false;
        }

        // value must not be an empty string since because an empty value can not be compiled
        if ($data['value'] === '') {
            return false;
        }

        // if no type is set just use the value and continue
        if (!isset($data['type'])) {
            return false;
        }

        return true;
    }

    private function getVariableDumpTemplate(): string
    {
        return <<<PHP_EOL
// ATTENTION! This file is auto generated by the #class# and should not be edited.

#variables#

PHP_EOL;
    }

    private function concatenateStyles(
        FileCollection $styleFiles,
        StorefrontPluginConfiguration $themeConfig,
        string $salesChannelId
    ): string {
        $concatenatedStyles = '';
        foreach ($styleFiles as $file) {
            $concatenatedStyles .= $this->themeFileImporter->getConcatenableStylePath($file, $themeConfig);
        }
        $concatenatedStylesEvent = new ThemeCompilerConcatenatedStylesEvent($concatenatedStyles, $salesChannelId);
        $this->eventDispatcher->dispatch($concatenatedStylesEvent);

        return $concatenatedStylesEvent->getConcatenatedStyles();
    }

    private function getConcatenatedScripts(
        FileCollection $scriptFiles,
        StorefrontPluginConfiguration $themeConfig,
        string $salesChannelId
    ): string {
        $concatenatedScripts = '';
        foreach ($scriptFiles as $file) {
            $concatenatedScripts .= $this->themeFileImporter->getConcatenableScriptPath($file, $themeConfig);
        }

        $concatenatedScriptsEvent = new ThemeCompilerConcatenatedScriptsEvent($concatenatedScripts, $salesChannelId);
        $this->eventDispatcher->dispatch($concatenatedScriptsEvent);

        return $concatenatedScriptsEvent->getConcatenatedScripts();
    }

    private function writeCompiledFiles(
        string $themePrefix,
        string $compiled,
        string $concatenatedScripts,
        bool $withAssets,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection
    ): void {
        $path = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;

        if ($this->filesystem->has($path)) {
            $this->filesystem->deleteDirectory($path);
        }

        $cssFilePath = $path . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'all.css';
        $this->filesystem->write($cssFilePath, $compiled);

        $scriptFilepath = $path . \DIRECTORY_SEPARATOR . 'js' . \DIRECTORY_SEPARATOR . 'all.js';
        $this->filesystem->write($scriptFilepath, $concatenatedScripts);

        // assets
        if ($withAssets) {
            $this->copyAssets($themeConfig, $configurationCollection, $path);
        }
    }
}
