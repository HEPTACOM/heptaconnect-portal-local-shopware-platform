<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ManufacturerUnpacker;

class ManufacturerReceiver extends ReceiverContract
{
    private DalAccess $dal;

    private ManufacturerUnpacker $manufacturerUnpacker;

    public function __construct(DalAccess $dal, ManufacturerUnpacker $manufacturerUnpacker)
    {
        $this->dal = $dal;
        $this->manufacturerUnpacker = $manufacturerUnpacker;
    }

    public function supports(): string
    {
        return Manufacturer::class;
    }

    /**
     * @param Manufacturer $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $this->dal->repository('product_manufacturer')->upsert([$this->manufacturerUnpacker->unpack($entity)], $this->dal->getContext());
    }
}
