<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Api;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package business-ops
 *
 * @Route(defaults={"_routeScope"={"api"}})
 */
class RuleConfigController extends AbstractController
{
    /**
     * @var array<string, mixed[]>
     */
    private array $config = [];

    /**
     * @internal
     *
     * @param iterable<Rule> $taggedRules
     */
    public function __construct(iterable $taggedRules)
    {
        $this->hydrateConfig($taggedRules);
    }

    /**
     * @Since("6.5.0.0")
     * @Route("/api/_info/rule-config", name="api.info.rule-config", methods={"GET"})
     */
    public function getConditionsConfig(): JsonResponse
    {
        return new JsonResponse($this->config);
    }

    /**
     * @param iterable<Rule> $taggedRules
     */
    private function hydrateConfig(iterable $taggedRules): void
    {
        foreach ($taggedRules as $rule) {
            try {
                $config = $rule->getConfig();
            } catch (\Throwable $exception) {
                continue;
            }

            if ($config === null) {
                continue;
            }

            $this->config[$rule->getName()] = $config->getData();
        }
    }
}
