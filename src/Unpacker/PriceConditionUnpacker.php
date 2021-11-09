<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Framework\Rule\Rule;

class PriceConditionUnpacker
{
    public const NAME = 'heptaConnectName';

    public const ESSENCE = 'heptaConnectEssence';

    private DalAccess $dal;

    private ?array $salesChannelNames = null;

    public function __construct(DalAccess $dal)
    {
        $this->dal = $dal;
    }

    public function unpack(Condition $condition): array
    {
        if ($condition instanceof Condition\ValidityPeriodCondition) {
            $begin = $condition->getBegin();
            $end = $condition->getEnd();
            $essence = [
                'type' => 'dateRange',
            ];
            $nameParts = [];

            if ($begin instanceof \DateTimeInterface) {
                $essence['fromDate'] = $begin->getTimestamp();
                $nameParts[] = \sprintf('from %s', $begin->format('d.m.Y'));
            }

            if ($end instanceof \DateTimeInterface) {
                $essence['toDate'] = $end->getTimestamp();
                $nameParts[] = \sprintf('to %s', $end->format('d.m.Y'));
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

        if ($condition instanceof Condition\SalesChannelCondition) {
            $salesChannelId = $condition->getSalesChannel()->getPrimaryKey();

            return [
                'type' => 'salesChannel',
                'value' => [
                    'salesChannelIds' => [$salesChannelId],
                    'operator' => Rule::OPERATOR_EQ,
                ],
                self::NAME => \sprintf('Saleschannel %s', $this->getSalesChannelNames()[$salesChannelId] ?? $salesChannelId),
                self::ESSENCE => [
                    'type' => 'salesChannel',
                    'salesChannelId' => $salesChannelId,
                ],
            ];
        }

        throw new \Exception(\sprintf('Unsupported condition: %s', \get_class($condition)));
    }

    protected function getSalesChannelNames(): array
    {
        return $this->salesChannelNames ??= $this->dal->queryValueById('sales_channel', 'name');
    }
}
