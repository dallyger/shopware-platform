<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class CustomerNumberRule extends Rule
{
    public const RULE_NAME = 'customerCustomerNumber';

    /**
     * @var list<string>|null
     */
    protected ?array $numbers = null;

    protected string $operator;

    /**
     * @internal
     *
     * @param list<string>|null $numbers
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $numbers = null)
    {
        parent::__construct();
        $this->operator = $operator;
        $this->numbers = $numbers;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!\is_array($this->numbers)) {
            throw new UnsupportedValueException(\gettype($this->numbers), self::class);
        }

        return RuleComparison::stringArray($customer->getCustomerNumber(), array_map('strtolower', $this->numbers), $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'numbers' => RuleConstraints::stringArray(),
            'operator' => RuleConstraints::stringOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->taggedField('numbers');
    }
}
