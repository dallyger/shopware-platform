<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @package customer-order
 *
 * @extends EntityCollection<OrderLineItemEntity>
 */
class OrderLineItemCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderLineItemEntity $orderLineItem) {
            return $orderLineItem->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderLineItemEntity $orderLineItem) use ($id) {
            return $orderLineItem->getOrderId() === $id;
        });
    }

    public function sortByCreationDate(string $sortDirection = FieldSorting::ASCENDING): void
    {
        $this->sort(function (OrderLineItemEntity $a, OrderLineItemEntity $b) use ($sortDirection) {
            if ($sortDirection === FieldSorting::ASCENDING) {
                return $a->getCreatedAt() <=> $b->getCreatedAt();
            }

            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });
    }

    public function sortByPosition(): void
    {
        $this->sort(function (OrderLineItemEntity $a, OrderLineItemEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });
    }

    /**
     * @return array<mixed>
     */
    public function getPayloadsProperty(string $property): array
    {
        return $this->fmap(function (OrderLineItemEntity $lineItem) use ($property) {
            $payload = $lineItem->getPayload() ?? [];
            if (\array_key_exists($property, $payload)) {
                return $payload[$property];
            }

            return null;
        });
    }

    public function filterByType(string $type): self
    {
        return $this->filter(function (OrderLineItemEntity $lineItem) use ($type) {
            return $lineItem->getType() === $type;
        });
    }

    /**
     * @return OrderLineItemEntity[]
     */
    public function filterGoodsFlat(): array
    {
        $lineItems = $this->buildFlat($this);

        $filtered = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getGood()) {
                $filtered[] = $lineItem;
            }
        }

        return $filtered;
    }

    public function hasLineItemWithState(string $state): bool
    {
        foreach ($this->buildFlat($this) as $lineItem) {
            if (\in_array($state, $lineItem->getStates(), true)) {
                return true;
            }
        }

        return false;
    }

    public function getApiAlias(): string
    {
        return 'order_line_item_collection';
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            array_filter(array_map(static function (OrderLineItemEntity $orderLineItem) {
                return $orderLineItem->getPrice();
            }, array_values($this->getElements())))
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderLineItemEntity::class;
    }

    /**
     * @return OrderLineItemEntity[]
     */
    private function buildFlat(?OrderLineItemCollection $lineItems): array
    {
        $flat = [];
        if (!$lineItems) {
            return $flat;
        }

        foreach ($lineItems as $lineItem) {
            $flat[] = $lineItem;

            foreach ($this->buildFlat($lineItem->getChildren()) as $nest) {
                $flat[] = $nest;
            }
        }

        return $flat;
    }
}
