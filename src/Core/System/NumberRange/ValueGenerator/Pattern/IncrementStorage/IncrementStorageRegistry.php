<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Shopware\Core\System\NumberRange\Exception\IncrementStorageNotFoundException;

/**
 * @package checkout
 */
class IncrementStorageRegistry
{
    /**
     * @var AbstractIncrementStorage[]
     */
    private array $storages;

    private string $configuredStorage;

    /**
     * @internal
     *
     * @param AbstractIncrementStorage[] $storages
     */
    public function __construct(iterable $storages, string $configuredStorage)
    {
        $this->storages = $storages instanceof \Traversable ? iterator_to_array($storages) : $storages;
        $this->configuredStorage = $configuredStorage;
    }

    public function getStorage(?string $storage = null): AbstractIncrementStorage
    {
        if ($storage === null) {
            $storage = $this->configuredStorage;
        }

        if (!isset($this->storages[$storage])) {
            throw new IncrementStorageNotFoundException($storage, array_keys($this->storages));
        }

        return $this->storages[$storage];
    }

    public function migrate(string $from, string $to): void
    {
        $fromStorage = $this->getStorage($from);
        $toStorage = $this->getStorage($to);

        foreach ($fromStorage->list() as $numberRangeId => $state) {
            $toStorage->set($numberRangeId, $state);
        }
    }
}
