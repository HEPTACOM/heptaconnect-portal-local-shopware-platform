<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition\ValidityPeriodCondition;

class PriceConditionUnpacker
{
    public const NAME = 'heptaConnectName';

    public const ESSENCE = 'heptaConnectEssence';

    public function unpack(Condition $condition): array
    {
        if ($condition instanceof ValidityPeriodCondition) {
            $begin = $condition->getBegin();
            $end = $condition->getEnd();
            $essence = [
                'type' => 'dateRange',
            ];
            $nameParts = [];

            if ($begin instanceof \DateTimeInterface) {
                $essence['fromDate'] = $begin->getTimestamp();
                $nameParts[] = \sprintf('vom %s', $begin->format('d.m.Y'));
            }

            if ($end instanceof \DateTimeInterface) {
                $essence['toDate'] = $end->getTimestamp();
                $nameParts[] = \sprintf('bis zum %s', $end->format('d.m.Y'));
            }

            return [
                'type' => 'dateRange',
                'value' => [
                    'useTime' => false,
                    'fromDate' => ($begin ?? \date_create_from_format('U', '0'))->format(\DateTimeInterface::ATOM),
                    'toDate' => ($end ?? \date_create('1000 year'))->format(\DateTimeInterface::ATOM),
                ],
                self::NAME => \implode(' ', $nameParts),
                self::ESSENCE => $essence,
            ];
        }

        throw new \Exception('Unsupported condition: ' . \get_class($condition));
    }
}
