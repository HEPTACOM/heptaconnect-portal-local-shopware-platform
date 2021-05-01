<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerPriceGroup;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\StorageHelper;
use Ramsey\Uuid\Uuid;

class CustomerPriceGroupReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return CustomerPriceGroup::class;
    }

    /**
     * @param CustomerPriceGroup $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $primaryKey = PrimaryKeyGenerator::generatePrimaryKey($entity, '56636118-4306-44fe-9ebc-4584e9c706af') ?? Uuid::uuid5('7bde4c47-bc51-45db-a1b7-093c60170a79', $entity->getCode())->getHex();
        $entity->setPrimaryKey($primaryKey);
        $mapping->setExternalId($primaryKey);
        $container = $context->getContainer($mapping);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);

        $dalAccess->repository('tag')->upsert([[
            'id' => $primaryKey,
            'name' => $entity->getCode(),
        ]], $dalAccess->getContext());

        StorageHelper::addCustomerPriceGroupTagId($primaryKey, $context->getStorage($mapping));
    }
}
