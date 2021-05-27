<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyValue;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyValueUnpacker;

class PropertyValueReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return PropertyValue::class;
    }

    /**
     * @param PropertyValue $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer($mapping);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        /** @var PropertyValueUnpacker $unpacker */
        $unpacker = $container->get(PropertyValueUnpacker::class);

        $dalAccess->repository('property_group_option')->upsert([$unpacker->unpack($entity)], $dalAccess->getContext());
    }
}
