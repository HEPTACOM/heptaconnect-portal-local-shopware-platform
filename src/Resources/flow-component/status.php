<?php

declare(strict_types=1);

use Heptacom\HeptaConnect\Portal\Base\Builder\FlowComponent;
use Heptacom\HeptaConnect\Portal\Base\StatusReporting\Contract\StatusReporterContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Defaults;

FlowComponent::statusReporter(StatusReporterContract::TOPIC_HEALTH)
    ->run(static fn (DalAccess $dal): bool => $dal->idExists('sales_channel', Defaults::SALES_CHANNEL));
