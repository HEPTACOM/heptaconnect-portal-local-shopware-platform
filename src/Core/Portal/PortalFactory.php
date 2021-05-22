<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Core\Portal;

use Heptacom\HeptaConnect\Core\Portal\Contract\PortalFactoryContract;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalContract;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalExtensionContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Portal as LocalShopwarePlatformPortal;

class PortalFactory extends PortalFactoryContract
{
    private PortalFactoryContract $portalFactory;

    private LocalShopwarePlatformPortal $localShopwarePlatformPortal;

    public function __construct(
        PortalFactoryContract $portalFactory,
        LocalShopwarePlatformPortal $localShopwarePlatformPortal
    ) {
        $this->portalFactory = $portalFactory;
        $this->localShopwarePlatformPortal = $localShopwarePlatformPortal;
    }

    public function instantiatePortal(string $class): PortalContract
    {
        switch ($class) {
            case LocalShopwarePlatformPortal::class:
                return $this->localShopwarePlatformPortal;
            default:
                return $this->portalFactory->instantiatePortal($class);
        }
    }

    public function instantiatePortalExtension(string $class): PortalExtensionContract
    {
        switch ($class) {
            default:
                return $this->portalFactory->instantiatePortalExtension($class);
        }
    }
}
