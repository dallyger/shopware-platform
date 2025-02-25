<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @package checkout
 */
class CartService implements ResetInterface
{
    public const SALES_CHANNEL = 'sales-channel';

    /**
     * @var Cart[]
     */
    private array $cart = [];

    private EventDispatcherInterface $eventDispatcher;

    private AbstractCartLoadRoute $loadRoute;

    private AbstractCartDeleteRoute $deleteRoute;

    private CartCalculator $calculator;

    private AbstractCartItemUpdateRoute $itemUpdateRoute;

    private AbstractCartItemRemoveRoute $itemRemoveRoute;

    private AbstractCartItemAddRoute $itemAddRoute;

    private AbstractCartOrderRoute $orderRoute;

    private AbstractCartPersister $persister;

    /**
     * @internal
     */
    public function __construct(
        AbstractCartPersister $persister,
        EventDispatcherInterface $eventDispatcher,
        CartCalculator $calculator,
        AbstractCartLoadRoute $loadRoute,
        AbstractCartDeleteRoute $deleteRoute,
        AbstractCartItemAddRoute $itemAddRoute,
        AbstractCartItemUpdateRoute $itemUpdateRoute,
        AbstractCartItemRemoveRoute $itemRemoveRoute,
        AbstractCartOrderRoute $orderRoute
    ) {
        $this->persister = $persister;
        $this->eventDispatcher = $eventDispatcher;
        $this->loadRoute = $loadRoute;
        $this->deleteRoute = $deleteRoute;
        $this->calculator = $calculator;
        $this->itemUpdateRoute = $itemUpdateRoute;
        $this->itemRemoveRoute = $itemRemoveRoute;
        $this->itemAddRoute = $itemAddRoute;
        $this->orderRoute = $orderRoute;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart[$cart->getToken()] = $cart;
    }

    public function createNew(string $token, string $name = self::SALES_CHANNEL): Cart
    {
        $cart = new Cart($name, $token);

        $this->eventDispatcher->dispatch(new CartCreatedEvent($cart));

        return $this->cart[$cart->getToken()] = $cart;
    }

    public function getCart(
        string $token,
        SalesChannelContext $context,
        string $name = self::SALES_CHANNEL,
        bool $caching = true
    ): Cart {
        if ($caching && isset($this->cart[$token])) {
            return $this->cart[$token];
        }

        $request = new Request();
        $request->query->set('name', $name);
        $request->query->set('token', $token);

        $cart = $this->loadRoute->load($request, $context)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @param LineItem|LineItem[] $items
     *
     * @throws CartException
     */
    public function add(Cart $cart, $items, SalesChannelContext $context): Cart
    {
        if ($items instanceof LineItem) {
            $items = [$items];
        }

        $cart = $this->itemAddRoute->add(new Request(), $cart, $context, $items)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws CartException
     */
    public function changeQuantity(Cart $cart, string $identifier, int $quantity, SalesChannelContext $context): Cart
    {
        $request = new Request();
        $request->request->set('items', [
            [
                'id' => $identifier,
                'quantity' => $quantity,
            ],
        ]);

        $cart = $this->itemUpdateRoute->change($request, $cart, $context)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws CartException
     */
    public function remove(Cart $cart, string $identifier, SalesChannelContext $context): Cart
    {
        $request = new Request();
        $request->request->set('ids', [$identifier]);

        $cart = $this->itemRemoveRoute->remove($request, $cart, $context)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws InvalidOrderException
     * @throws InconsistentCriteriaIdsException
     */
    public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): string
    {
        $orderId = $this->orderRoute->order($cart, $context, $data)->getOrder()->getId();

        if (isset($this->cart[$cart->getToken()])) {
            unset($this->cart[$cart->getToken()]);
        }

        $cart = $this->createNew($context->getToken(), $cart->getName());
        $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

        return $orderId;
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        $cart = $this->calculator->calculate($cart, $context);
        $this->persister->save($cart, $context);

        return $cart;
    }

    public function deleteCart(SalesChannelContext $context): void
    {
        $this->deleteRoute->delete($context);
    }

    public function reset(): void
    {
        $this->cart = [];
    }
}
