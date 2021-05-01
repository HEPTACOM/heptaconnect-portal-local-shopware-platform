<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\StatusReporter;

use Heptacom\HeptaConnect\Portal\Base\StatusReporting\Contract\StatusReporterContract;
use Heptacom\HeptaConnect\Portal\Base\StatusReporting\Contract\StatusReportingContextInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class HealthStatusReporter extends StatusReporterContract
{
    public function supportsTopic(): string
    {
        return self::TOPIC_HEALTH;
    }

    protected function run(StatusReportingContextInterface $context): array
    {
        $result = [$this->supportsTopic() => true];
        /** @var DalAccess $dalAccess */
        $dalAccess = $context->getContainer()->get(DalAccess::class);
        $salesChannelRepository = $dalAccess->repository('sales_channel');

        try {
            $salesChannelRepository->searchIds(new Criteria(), $dalAccess->getContext());
        } catch (\Throwable $exception) {
            $result[$this->supportsTopic()] = false;
            $result['message'] = $exception->getMessage();
        }

        return $result;
    }
}
