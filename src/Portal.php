<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform;

use Heptacom\HeptaConnect\Core\Storage\NormalizationRegistry;
use Heptacom\HeptaConnect\Portal\Base\Emission\EmitterCollection;
use Heptacom\HeptaConnect\Portal\Base\Exploration\ExplorerCollection;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalContract;
use Heptacom\HeptaConnect\Portal\Base\Reception\ReceiverCollection;
use Heptacom\HeptaConnect\Portal\Base\StatusReporting\StatusReporterCollection;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\StateMachineTransitionWalker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Strategy\CustomerSalesChannelStrategyContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

class Portal extends PortalContract
{
    private RequestStack $requestStack;

    private NormalizationRegistry $normalizationRegistry;

    private FileSaver $fileSaver;

    private MediaService $mediaService;

    private ContainerInterface $container;

    private StateMachineRegistry $stateMachineRegistry;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    public function __construct(
        RequestStack $requestStack,
        NormalizationRegistry $normalizationRegistry,
        FileSaver $fileSaver,
        MediaService $mediaService,
        ContainerInterface $container,
        StateMachineRegistry $stateMachineRegistry,
        DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
        $this->requestStack = $requestStack;
        $this->normalizationRegistry = $normalizationRegistry;
        $this->fileSaver = $fileSaver;
        $this->mediaService = $mediaService;
        $this->container = $container;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
    }

    public function getExplorers(): ExplorerCollection
    {
        return new ExplorerCollection([
            new Explorer\CustomerExplorer(),
            new Explorer\CustomerGroupExplorer(),
            new Explorer\OrderExplorer(),
            new Explorer\ProductExplorer(),
            new Explorer\SalutationExplorer(),
            new Explorer\CurrencyExplorer(),
            new Explorer\ShippingMethodExplorer(),
            new Explorer\PaymentMethodExplorer(),
            new Explorer\CategoryExplorer(),
            new Explorer\UnitExplorer(),
        ]);
    }

    public function getEmitters(): EmitterCollection
    {
        return new EmitterCollection([
            new Emitter\CategoryEmitter(),
            new Emitter\CustomerGroupEmitter(),
            new Emitter\ProductEmitter(),
            new Emitter\CustomerEmitter(),
            new Emitter\OrderEmitter(),
            new Emitter\UnitEmitter(),
        ]);
    }

    public function getReceivers(): ReceiverCollection
    {
        return new ReceiverCollection([
            new Receiver\CustomerReceiver(),
            new Receiver\CustomerGroupReceiver(),
            new Receiver\CustomerPriceGroupReceiver(),
            new Receiver\MediaReceiver(),
            new Receiver\CategoryReceiver(),
            new Receiver\OrderReceiver(),
            new Receiver\ProductReceiver(),
            new Receiver\UnitReceiver(),
        ]);
    }

    public function getStatusReporters(): StatusReporterCollection
    {
        return new StatusReporterCollection([
            new StatusReporter\HealthStatusReporter(),
        ]);
    }

    public function getServices(): array
    {
        $result = parent::getServices();

        $result[ExistingIdentifierCache::class] = static fn (ContainerInterface $c): ExistingIdentifierCache => new ExistingIdentifierCache(
            $c->get(DalAccess::class)
        );
        $result[FileSaver::class] = $this->fileSaver;
        $result[MediaService::class] = $this->mediaService;
        $result[NormalizationRegistry::class] = $this->normalizationRegistry;
        $result[RequestStack::class] = $this->requestStack;
        $result[StateMachineTransitionWalker::class] = function (ContainerInterface $c): StateMachineTransitionWalker {
            /** @var DalAccess $dalAccess */
            $dalAccess = $c->get(DalAccess::class);

            return new StateMachineTransitionWalker(
                $this->stateMachineRegistry,
                $this->definitionInstanceRegistry,
                $dalAccess->repository('state_machine_state')
            );
        };
        $result[Translator::class] = static fn (ContainerInterface $c): Translator => new Translator($c->get(DalAccess::class));
        $result[DalAccess::class] = static fn (ContainerInterface $c): DalAccess => new DalAccess($c->get('shopware_container'));
        $result['shopware_container'] = $this->container;
        $result[Packer\CustomerPacker::class] = static fn (ContainerInterface $c): Packer\CustomerPacker => new Packer\CustomerPacker(
            $c->get(DalAccess::class)
        );
        $result[Packer\OrderStatePacker::class] = new Packer\OrderStatePacker();
        $result[CustomerSalesChannelStrategyContract::class] = static fn (ContainerInterface $c) => new CustomerSalesChannelStrategyContract();

        $result[Unpacker\ManufacturerUnpacker::class] = static fn (ContainerInterface $c): Unpacker\ManufacturerUnpacker => new Unpacker\ManufacturerUnpacker();
        $result[Unpacker\MediaUnpacker::class] = static fn (ContainerInterface $c): Unpacker\MediaUnpacker => new Unpacker\MediaUnpacker(
            $c->get(MediaService::class),
            $c->get(NormalizationRegistry::class),
            $c->get(DalAccess::class)
        );
        $result[Unpacker\ProductUnpacker::class] = static fn (ContainerInterface $c): Unpacker\ProductUnpacker => new Unpacker\ProductUnpacker(
            $c->get(DalAccess::class),
            $c->get(ExistingIdentifierCache::class),
            $c->get(Unpacker\MediaUnpacker::class),
            $c->get(Unpacker\ManufacturerUnpacker::class)
        );

        return $result;
    }
}
