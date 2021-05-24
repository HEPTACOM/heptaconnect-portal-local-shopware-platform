<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ProductReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return Product::class;
    }

    /**
     * @param Product $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer($mapping);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $productRepository = $dalAccess->repository( 'product');
        $dalContext = $dalAccess->getContext();
        /** @var ProductUnpacker $unpacker */
        $unpacker = $container->get(ProductUnpacker::class);
        $target = $unpacker->unpack($entity);
        $criteria = (new Criteria())->addFilter(new EqualsFilter('productId', $target['id']));
        $productPriceRepository = $dalAccess->repository('product_price');
        $deleteProductPriceIds = $productPriceRepository->searchIds($criteria, $dalContext)->getIds();
        $deleteProductPriceIds = \array_map(fn (string $id) => ['id' => $id], $deleteProductPriceIds);

        if (!empty($deleteProductPriceIds)) {
            // TODO optimize to delete on unused
            $productPriceRepository->delete($deleteProductPriceIds, $dalContext);
        }

        // separate cover update to allow correct dependency order
        $coverId = $target['coverId'];
        unset($target['coverId']);

        $productRepository->upsert([$target], $dalContext);
        $productRepository->update([[
            'id' => $target['id'],
            'coverId' => $coverId,
        ]], $dalContext);
    }
}
