<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Core\Portal;

use Heptacom\HeptaConnect\Core\Portal\Contract\PortalStackServiceContainerBuilderInterface;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalContract;
use Heptacom\HeptaConnect\Portal\Base\Portal\PortalExtensionCollection;
use Heptacom\HeptaConnect\Portal\Base\StorageKey\Contract\PortalNodeKeyInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Portal;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\RequestStack;

class ShopwarePortalStackServiceContainerBuilder implements PortalStackServiceContainerBuilderInterface
{
    private PortalStackServiceContainerBuilderInterface $decorated;

    private StateMachineRegistry $stateMachineRegistry;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private RequestStack $requestStack;

    private FileSaver $fileSaver;

    private MediaService $mediaService;

    private ContainerInterface $container;

    public function __construct(
        PortalStackServiceContainerBuilderInterface $decorated,
        StateMachineRegistry $stateMachineRegistry,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        RequestStack $requestStack,
        FileSaver $fileSaver,
        MediaService $mediaService,
        ContainerInterface $container
    ) {
        $this->decorated = $decorated;

        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->requestStack = $requestStack;
        $this->fileSaver = $fileSaver;
        $this->mediaService = $mediaService;
        $this->container = $container;
    }

    public function build(
        PortalContract $portal,
        PortalExtensionCollection $portalExtensions,
        PortalNodeKeyInterface $portalNodeKey
    ): ContainerBuilder {
        $result = $this->decorated->build($portal, $portalExtensions, $portalNodeKey);

        if ($portal instanceof Portal) {
            $this->prepareContainer($result);
        }

        return $result;
    }

    private function prepareContainer(ContainerBuilder $containerBuilder)
    {
        $this->setSyntheticServices($containerBuilder, [
            StateMachineRegistry::class => $this->stateMachineRegistry,
            DefinitionInstanceRegistry::class => $this->definitionInstanceRegistry,
            RequestStack::class => $this->requestStack,
            FileSaver::class => $this->fileSaver,
            MediaService::class => $this->mediaService,
            'shopware_service_container' => $this->container,
        ]);
    }

    /**
     * @param object[] $services
     */
    private function setSyntheticServices(ContainerBuilder $containerBuilder, array $services): void
    {
        foreach ($services as $id => $service) {
            $definitionId = (string)$id;
            $containerBuilder->set($definitionId, $service);
            $definition = (new Definition())
                ->setSynthetic(true)
                ->setClass(\get_class($service));
            $containerBuilder->setDefinition($definitionId, $definition);
        }
    }
}
