<?php declare(strict_types=1);

namespace unit\php\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\GrantDownloadAccessAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Action\GrantDownloadAccessAction
 */
class GrantDownloadAccessActionTest extends TestCase
{
    /**
     * @var MockObject|EntityRepository
     */
    private $orderLineItemDownloadRepository;

    private GrantDownloadAccessAction $action;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $updatePayload = [];

    public function setUp(): void
    {
        $this->orderLineItemDownloadRepository = $this->createMock(EntityRepository::class);
        $this->orderLineItemDownloadRepository->method('update')->willReturnCallback(
            function (array $payload, Context $context): EntityWrittenContainerEvent {
                $this->updatePayload = $payload;

                return new EntityWrittenContainerEvent($context, new NestedEventCollection([]), []);
            }
        );
        $this->action = new GrantDownloadAccessAction($this->orderLineItemDownloadRepository);

        $this->updatePayload = [];
    }

    public function testGetName(): void
    {
        static::assertEquals('action.grant.download.access', $this->action::getName());
    }

    public function testGetRequirements(): void
    {
        static::assertEquals([OrderAware::class], $this->action->requirements());
    }

    /**
     * @param array<int, array<string, mixed>> $expectedPayload
     *
     * @dataProvider orderProvider
     */
    public function testSetAccessHandleFlow(?OrderEntity $orderEntity, array $expectedPayload, bool $value = true): void
    {
        if ($orderEntity) {
            $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [OrderAware::ORDER => $orderEntity]);
        } else {
            $flow = new StorableFlow('foo', Context::createDefaultContext());
        }
        $flow->setConfig(['value' => $value]);

        $this->action->handleFlow($flow);

        static::assertEquals($expectedPayload, $this->updatePayload);
    }

    public function orderProvider(): \Generator
    {
        yield 'no order found' => [null, []];

        $order = new OrderEntity();

        yield 'order without line items' => [$order, []];

        $order = new OrderEntity();

        $lineItem = new OrderLineItemEntity();
        $lineItem->setGood(true);
        $lineItem->setId(Uuid::randomHex());

        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        yield 'order without downloadable line items' => [$order, []];

        $order = new OrderEntity();

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setGood(true);
        $lineItem->setStates([State::IS_DOWNLOAD]);

        $downloadId = Uuid::randomHex();
        $download = new OrderLineItemDownloadEntity();
        $download->setId($downloadId);

        $lineItem->setDownloads(new OrderLineItemDownloadCollection([$download]));

        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        yield 'grant access for order with downloadable line items' => [
            $order,
            [
                [
                    'id' => $downloadId,
                    'accessGranted' => true,
                ],
            ],
        ];

        yield 'revoke access for order with downloadable line items' => [
            $order,
            [
                [
                    'id' => $downloadId,
                    'accessGranted' => false,
                ],
            ],
            false,
        ];
    }
}
