<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemPropertyRule extends Rule
{
    public const RULE_NAME = 'cartLineItemProperty';

    /**
     * @var list<string>
     */
    protected array $identifiers;

    protected string $operator;

    /**
     * @param list<string> $identifiers
     *
     * @internal
     */
    public function __construct(array $identifiers = [], string $operator = self::OPERATOR_EQ)
    {
        parent::__construct();
        $this->identifiers = $identifiers;
        $this->operator = $operator;
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatch($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->lineItemMatch($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    private function lineItemMatch(LineItem $lineItem): bool
    {
        $properties = $lineItem->getPayloadValue('propertyIds') ?? [];
        $options = $lineItem->getPayloadValue('optionIds') ?? [];

        /** @var list<string> $ids */
        $ids = array_merge($properties, $options);

        return RuleComparison::uuids($ids, $this->identifiers, $this->operator);
    }
}
