<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\OrderAware;

/**
 * @package business-ops
 *
 * @internal
 */
class GrantDownloadAccessAction extends FlowAction implements DelayableAction
{
    private EntityRepository $orderLineItemDownloadRepository;

    public function __construct(EntityRepository $orderLineItemDownloadRepository)
    {
        $this->orderLineItemDownloadRepository = $orderLineItemDownloadRepository;
    }

    public static function getName(): string
    {
        return 'action.grant.download.access';
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER)) {
            return;
        }

        /** @var OrderEntity $order */
        $order = $flow->getData(OrderAware::ORDER);

        $this->update($flow->getContext(), $flow->getConfig(), $order);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, OrderEntity $order): void
    {
        if (!isset($config['value'])) {
            return;
        }

        $lineItems = $order->getLineItems();

        if (!$lineItems) {
            return;
        }

        $downloadIds = [];

        foreach ($lineItems->filterGoodsFlat() as $lineItem) {
            $states = $lineItem->getStates();

            if (!$lineItem->getDownloads() || !\in_array(State::IS_DOWNLOAD, $states, true)) {
                continue;
            }

            /** @var OrderLineItemDownloadEntity $download */
            foreach ($lineItem->getDownloads() as $download) {
                $downloadIds[] = $download->getId();
                $download->setAccessGranted((bool) $config['value']);
            }
        }

        if (empty($downloadIds)) {
            return;
        }

        $this->orderLineItemDownloadRepository->update(
            array_map(function (string $id) use ($config): array {
                return ['id' => $id, 'accessGranted' => $config['value']];
            }, array_unique($downloadIds)),
            $context
        );
    }
}
