<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Exception\CartDeserializeFailedException;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartPersister implements CartPersisterInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load(string $token, SalesChannelContext $context): Cart
    {
        $content = $this->connection->fetchColumn(
            'SELECT `cart`.`cart` FROM cart WHERE `token` = :token',
            ['token' => $token]
        );

        if ($content === false) {
            throw new CartTokenNotFoundException($token);
        }

        $cart = \unserialize((string) $content);
        if (!$cart instanceof Cart) {
            throw new CartDeserializeFailedException();
        }

        $cart->setToken($token);

        return $cart;
    }

    /**
     * @throws InvalidUuidException
     */
    public function save(Cart $cart, SalesChannelContext $context): void
    {
        //prevent empty carts
        if ($cart->getLineItems()->count() <= 0) {
            $this->delete($cart->getToken(), $context);

            return;
        }

        $this->delete($cart->getToken(), $context);

        $customerId = $context->getCustomer() ? Uuid::fromHexToBytes($context->getCustomer()->getId()) : null;

        $data = [
            'token' => $cart->getToken(),
            'name' => $cart->getName(),
            'currency_id' => Uuid::fromHexToBytes($context->getCurrency()->getId()),
            'shipping_method_id' => Uuid::fromHexToBytes($context->getShippingMethod()->getId()),
            'payment_method_id' => Uuid::fromHexToBytes($context->getPaymentMethod()->getId()),
            'country_id' => Uuid::fromHexToBytes($context->getShippingLocation()->getCountry()->getId()),
            'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannel()->getId()),
            'customer_id' => $customerId,
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getLineItems()->count(),
            'cart' => $this->serializeCart($cart),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ];

        $this->connection->insert('cart', $data);
    }

    public function delete(string $token, SalesChannelContext $context): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM cart WHERE `token` = :token',
            ['token' => $token]
        );
    }

    private function serializeCart(Cart $cart): string
    {
        $errors = $cart->getErrors();
        $cart->setErrors(new ErrorCollection());
        $serializedCart = \serialize($cart);
        $cart->setErrors($errors);

        return $serializedCart;
    }
}
