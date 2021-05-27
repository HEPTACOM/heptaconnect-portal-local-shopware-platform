<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyGroup;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyGroupUnpacker;

class PropertyGroupReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return PropertyGroup::class;
    }

    /**
     * @param PropertyGroup $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer($mapping);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        /** @var PropertyGroupUnpacker $unpacker */
        $unpacker = $container->get(PropertyGroupUnpacker::class);

        $dalAccess->repository('property_group')->upsert([$unpacker->unpack($entity)], $dalAccess->getContext());
    }
}
