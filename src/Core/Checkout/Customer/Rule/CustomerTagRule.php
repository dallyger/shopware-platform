<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Tag\TagDefinition;

/**
 * @package business-ops
 */
class CustomerTagRule extends Rule
{
    public const RULE_NAME = 'customerTag';

    protected string $operator;

    /**
     * @var list<string>|null
     */
    protected ?array $identifiers = null;

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
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::uuids($this->extractTagIds($customer), $this->identifiers, $this->operator);
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
    private function extractTagIds(CustomerEntity $customer): array
    {
        $tagIds = $customer->getTagIds();

        if (!$tagIds) {
            return [];
        }

        return $tagIds;
    }
}
