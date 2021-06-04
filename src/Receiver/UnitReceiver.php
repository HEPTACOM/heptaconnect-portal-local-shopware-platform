<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\UnitUnpacker;

class UnitReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return Unit::class;
    }

    /**
     * @param Unit $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer();
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        /** @var UnitUnpacker $unpacker */
        $unpacker = $container->get(UnitUnpacker::class);

        $dalAccess->repository('unit')->upsert([$unpacker->unpack($entity)], $dalAccess->getContext());
    }
}
