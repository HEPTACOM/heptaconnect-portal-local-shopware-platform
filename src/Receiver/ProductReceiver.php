<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ProductReceiver extends ReceiverContract
{
    private DalAccess $dal;

    private ProductUnpacker $productUnpacker;

    public function __construct(DalAccess $dal, ProductUnpacker $productUnpacker)
    {
        $this->dal = $dal;
        $this->productUnpacker = $productUnpacker;
    }

    public function supports(): string
    {
        return Product::class;
    }

    /**
     * @param Product $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $productRepository = $this->dal->repository('product');
        $dalContext = $this->dal->getContext();
        $target = $this->productUnpacker->unpack($entity);
        $criteria = (new Criteria())->addFilter(new EqualsFilter('productId', $target['id']));
        $productPriceRepository = $this->dal->repository('product_price');
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
