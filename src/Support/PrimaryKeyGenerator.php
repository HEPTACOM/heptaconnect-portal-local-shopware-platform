<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Storage\Base\PrimaryKeySharingMappingStruct;
use Ramsey\Uuid\Uuid;

/**
 * @todo Provide this helper as a service
 */
class PrimaryKeyGenerator
{
    public static function generatePrimaryKey(DatasetEntityContract $entity, string $namespace): ?string
    {
        if ($entity->getPrimaryKey() !== null) {
            return $entity->getPrimaryKey();
        }

        $mapping = $entity->getAttachment(PrimaryKeySharingMappingStruct::class);

        if (!$mapping instanceof MappingInterface || $mapping->getExternalId() === null) {
            return null;
        }

        return (string) Uuid::uuid5($namespace, \implode(';', [
            \json_encode($mapping->getPortalNodeKey()),
            $mapping->getEntityType(),
            $mapping->getExternalId(),
        ]))->getHex();
    }
}
