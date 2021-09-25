<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Exception;

class DuplicateSyncOperationKeyPreventionException extends \Exception
{
    private string $syncOperationKey;

    public function __construct(string $syncOperationKey, int $code, ?\Throwable $throwable = null)
    {
        parent::__construct(\sprintf('Sync operation key "%s" is already in use and cannot be added', $syncOperationKey), $code, $throwable);
        $this->syncOperationKey = $syncOperationKey;
    }

    public function getSyncOperationKey(): string
    {
        return $this->syncOperationKey;
    }
}
