<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package customer-order
 *
 * @extends EntityCollection<CustomerGroupEntity>
 */
class CustomerGroupCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupEntity::class;
    }
}
