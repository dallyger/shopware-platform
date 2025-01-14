<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

/**
 * @package system-settings
 *
 * @internal
 *
 * @final
 */
class AdminElasticsearchHelper
{
    private bool $adminEsEnabled;

    private bool $refreshIndices;

    private string $adminIndexPrefix;

    public function __construct(bool $adminEsEnabled, bool $refreshIndices, string $adminIndexPrefix)
    {
        $this->adminEsEnabled = $adminEsEnabled;
        $this->refreshIndices = $refreshIndices;
        $this->adminIndexPrefix = $adminIndexPrefix;
    }

    public function getEnabled(): bool
    {
        return $this->adminEsEnabled;
    }

    /**
     * Only used for unit tests because the container parameter bag is frozen and can not be changed at runtime.
     * Therefore this function can be used to test different behaviours
     *
     * @internal
     */
    public function setEnabled(bool $enabled): self
    {
        $this->adminEsEnabled = $enabled;

        return $this;
    }

    public function getRefreshIndices(): bool
    {
        return $this->refreshIndices;
    }

    public function getPrefix(): string
    {
        return $this->adminIndexPrefix;
    }

    public function getIndex(string $name): string
    {
        return $this->adminIndexPrefix . '-' . \strtolower(\str_replace(['_', ' '], '-', $name));
    }
}
