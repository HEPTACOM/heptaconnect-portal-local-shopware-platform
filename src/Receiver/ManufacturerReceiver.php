<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ManufacturerUnpacker;

class ManufacturerReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return Manufacturer::class;
    }

    /**
     * @param Manufacturer $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        /** @var ManufacturerUnpacker $unpacker */
        $unpacker = $context->getContainer($mapping)->get(ManufacturerUnpacker::class);
        /** @var DalAccess $dal */
        $dal = $context->getContainer($mapping)->get(DalAccess::class);

        $dal->repository('manufacturer')->upsert([$unpacker->unpack($entity)], $dal->getContext());
    }
}
