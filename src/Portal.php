<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform;

use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalContract;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Portal extends PortalContract
{
    public const DAL_INDEX_MODE_NONE = 'none';

    public const DAL_INDEX_MODE_QUEUE = 'queue';

    public const DAL_INDEX_MODE_DIRECT = 'direct';

    public const CONFIG_DAL_INDEX_MODE = 'dal_indexing_mode';

    public function getPsr4(): array
    {
        return [];
    }

    public function getConfigurationTemplate(): OptionsResolver
    {
        return parent::getConfigurationTemplate()
            ->setDefaults([
                self::CONFIG_DAL_INDEX_MODE => self::DAL_INDEX_MODE_QUEUE,
            ])
            ->setAllowedTypes(self::CONFIG_DAL_INDEX_MODE, 'string')
            ->setAllowedValues(self::CONFIG_DAL_INDEX_MODE, [
                self::DAL_INDEX_MODE_DIRECT,
                self::DAL_INDEX_MODE_NONE,
                self::DAL_INDEX_MODE_QUEUE,
            ]);
    }
}
