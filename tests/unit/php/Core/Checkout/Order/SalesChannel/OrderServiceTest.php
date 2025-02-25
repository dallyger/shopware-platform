<?php declare(strict_types=1);

namespace unit\php\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\Validation\OrderValidationFactory;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Order\SalesChannel\OrderService
 */
class OrderServiceTest extends TestCase
{
    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var MockObject|CartService
     */
    private $cartService;

    /**
     * @var MockObject|EntityRepository
     */
    private $paymentMethodRepository;

    /**
     * @var MockObject|StateMachineRegistry
     */
    private $stateMachineRegistry;

    private OrderService $orderService;

    public function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->cartService = $this->createMock(CartService::class);
        $this->paymentMethodRepository = $this->createMock(EntityRepository::class);
        $this->stateMachineRegistry = $this->createMock(StateMachineRegistry::class);

        $this->orderService = new OrderService(
            new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            new OrderValidationFactory(),
            $this->eventDispatcher,
            $this->cartService,
            $this->paymentMethodRepository,
            $this->stateMachineRegistry
        );
    }

    public function testCreateOrderWithDigitalGoodsNeedsRevocationConfirm(): void
    {
        $dataBag = new DataBag();
        $dataBag->set('tos', true);
        $context = $this->createMock(SalesChannelContext::class);

        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('a', 'test'))->setStates([State::IS_PHYSICAL]));

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->expects(static::exactly(2))->method('order');

        $idSearchResult = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
        $this->paymentMethodRepository->method('searchIds')->willReturn($idSearchResult);

        $this->orderService->createOrder($dataBag, $context);

        $cart->add((new LineItem('b', 'test'))->setStates([State::IS_DOWNLOAD]));

        try {
            $this->orderService->createOrder($dataBag, $context);

            static::fail('Did not throw exception');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $errors = iterator_to_array($exception->getErrors());
            static::assertCount(1, $errors);
            static::assertEquals('VIOLATION::IS_BLANK_ERROR', $errors[0]['code']);
            static::assertEquals('/revocation', $errors[0]['source']['pointer']);
        }

        $dataBag->set('revocation', true);

        $this->orderService->createOrder($dataBag, $context);
    }
}
