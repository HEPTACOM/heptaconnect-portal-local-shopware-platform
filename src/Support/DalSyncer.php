<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

final class DalSyncer
{
    private SyncServiceInterface $sync;

    private Context $context;

    private LoggerInterface $logger;

    /**
     * @var SyncOperation[]
     */
    private array $operations = [];

    private function __construct(SyncServiceInterface $sync, Context $context, LoggerInterface $logger)
    {
        $this->sync = $sync;
        $this->context = $context;
        $this->logger = $logger;
    }

    public static function make(SyncServiceInterface $sync, Context $context, LoggerInterface $logger): self
    {
        return new self($sync, $context, $logger);
    }

    public function upsert(string $entity, iterable $items, ?string $key = null): self
    {
        $items = \iterable_to_array($items);

        if ($items === []) {
            return $this;
        }

        return $this->push(self::createSyncOperation(SyncOperation::ACTION_UPSERT, $entity, $items, $key));
    }

    public function delete(string $entity, iterable $items, ?string $key = null): self
    {
        $items = \iterable_to_array($items);

        if ($items === []) {
            return $this;
        }

        return $this->push(self::createSyncOperation(SyncOperation::ACTION_DELETE, $entity, $items, $key));
    }

    public function flush(): self
    {
        $operations = $this->operations;
        $itemCount = \count($this->operations);
        $this->operations = [];
        $this->sync->sync($operations, $this->context, new SyncBehavior(true, true));
        $this->logger->info(
            \sprintf('[DalSyncer::flush] %d items flushed', $itemCount),
            [
                'count' => $itemCount,
            ]
        );

        return $this;
    }

    public function clear(): self
    {
        $itemCount = \count($this->operations);
        $this->operations = [];
        $this->logger->info(
            \sprintf('[DalSyncer::clear] %d items cleared', $itemCount),
            [
                'count' => $itemCount,
            ]
        );

        return $this;
    }

    /**
     * @return SyncOperation[]
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function push(SyncOperation $operation): self
    {
        $this->logger->info(
            \sprintf('[DalSyncer::push] %s: %s on %s', $operation->getKey(), $operation->getAction(), $operation->getEntity()),
            [
                'operationKey' => $operation->getKey(),
                'operationAction' => $operation->getAction(),
                'operationEntity' => $operation->getEntity(),
            ]
        );

        $this->operations[$operation->getKey()] = $operation;

        return $this;
    }

    private static function createSyncOperation(string $action, string $entity, array $payload, ?string $key): SyncOperation
    {
        $key ??= Uuid::randomHex();
        $payload = \array_values($payload);

        if (\defined(PlatformRequest::class.'::API_VERSION')) {
            return new SyncOperation($key, $entity, $action, $payload, PlatformRequest::API_VERSION);
        }

        return new SyncOperation($key, $entity, $action, $payload);
    }
}
