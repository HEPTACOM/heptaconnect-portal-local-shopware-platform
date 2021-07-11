<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\TypedDatasetEntityCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

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

    protected function batch(TypedDatasetEntityCollection $entities, ReceiveContextInterface $context): void
    {
        $productIds = [];
        $productUpserts = [];

        foreach ($entities as $product) {
            $payload = $this->productUnpacker->unpack($product);
            $id = $payload['id'];
            $productIds[] = $id;
            $coverId = $payload['coverId'];

            // separate cover update to allow correct dependency order
            unset($payload['coverId']);

            $productUpserts[] = $payload;
            $productUpserts[] = [
                'id' => $id,
                'coverId' => $coverId,
            ];
        }

        $deleteProductPriceIds = [];

        if ($productIds !== []) {
            $productPriceRepository = $this->dal->repository('product_price');
            $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('productId', $productIds));
            $dalContext = $this->dal->getContext();

            // TODO optimize to delete on unused
            $deleteProductPriceIds = \array_map(
                static fn (string $id) => ['id' => $id],
                $productPriceRepository->searchIds($criteria, $dalContext)->getIds()
            );
        }

        $this->dal->createSyncer()
            ->delete('product_price', $deleteProductPriceIds)
            ->upsert('product', $productUpserts)
            ->flush();
    }
}
