<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Tag\TagDefinition;

/**
 * @package business-ops
 */
class OrderTagRule extends FlowRule
{
    public const RULE_NAME = 'orderTag';

    protected string $operator;

    /**
     * @var list<string>|null
     */
    protected ?array $identifiers;

    /**
     * @internal
     *
     * @param list<string>|null $identifiers
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        return RuleComparison::uuids($this->extractTagIds($scope->getOrder()), $this->identifiers, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['identifiers'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('identifiers', TagDefinition::ENTITY_NAME, true);
    }

    /**
     * @return list<string>
     */
    private function extractTagIds(OrderEntity $order): array
    {
        $tags = $order->getTags();

        if (!$tags) {
            return [];
        }

        return $tags->getIds();
    }
}
