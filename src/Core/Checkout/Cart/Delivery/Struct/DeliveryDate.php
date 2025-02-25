<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @package checkout
 */
class DeliveryDate extends Struct
{
    /**
     * @var \DateTimeImmutable
     */
    protected $earliest;

    /**
     * @var \DateTimeImmutable
     */
    protected $latest;

    public function __construct(\DateTimeInterface $earliest, \DateTimeInterface $latest)
    {
        $earliest = new \DateTimeImmutable($earliest->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $latest = new \DateTimeImmutable($latest->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $this->earliest = $earliest->setTime(16, 0);
        $this->latest = $latest->setTime(16, 0);
    }

    public static function createFromDeliveryTime(DeliveryTime $deliveryTime): self
    {
        switch ($deliveryTime->getUnit()) {
            case DeliveryTimeEntity::DELIVERY_TIME_HOUR:
                return new self(
                    self::create('PT' . $deliveryTime->getMin() . 'H'),
                    self::create('PT' . $deliveryTime->getMax() . 'H')
                );

            case DeliveryTimeEntity::DELIVERY_TIME_DAY:
                return new self(
                    self::create('P' . $deliveryTime->getMin() . 'D'),
                    self::create('P' . $deliveryTime->getMax() . 'D')
                );

            // NEXT-21735 - This is covered randomly
            // @codeCoverageIgnoreStart
            case DeliveryTimeEntity::DELIVERY_TIME_WEEK:
                return new self(
                    self::create('P' . $deliveryTime->getMin() . 'W'),
                    self::create('P' . $deliveryTime->getMax() . 'W')
                );
            // @codeCoverageIgnoreEnd

            case DeliveryTimeEntity::DELIVERY_TIME_MONTH:
                return new self(
                    self::create('P' . $deliveryTime->getMin() . 'M'),
                    self::create('P' . $deliveryTime->getMax() . 'M')
                );

            case DeliveryTimeEntity::DELIVERY_TIME_YEAR:
                return new self(
                    self::create('P' . $deliveryTime->getMin() . 'Y'),
                    self::create('P' . $deliveryTime->getMax() . 'Y')
                );

            default:
                throw new \RuntimeException(sprintf('Not supported unit %s', $deliveryTime->getUnit()));
        }
    }

    public function getEarliest(): \DateTimeImmutable
    {
        return $this->earliest;
    }

    public function getLatest(): \DateTimeImmutable
    {
        return $this->latest;
    }

    public function add(\DateInterval $interval): self
    {
        return new DeliveryDate(
            $this->earliest->add($interval),
            $this->latest->add($interval)
        );
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery_date';
    }

    private static function create(string $interval): \DateTime
    {
        return (new \DateTime())->add(new \DateInterval($interval));
    }
}
