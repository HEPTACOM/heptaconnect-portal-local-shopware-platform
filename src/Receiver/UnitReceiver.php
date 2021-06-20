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
    private DalAccess $dal;

    private UnitUnpacker $unitUnpacker;

    public function __construct(DalAccess $dal, UnitUnpacker $unitUnpacker)
    {
        $this->dal = $dal;
        $this->unitUnpacker = $unitUnpacker;
    }

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
        $this->dal->repository('unit')->upsert([$this->unitUnpacker->unpack($entity)], $this->dal->getContext());
    }
}
