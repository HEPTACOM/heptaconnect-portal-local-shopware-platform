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
            try {
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
            } catch (\Throwable $exception) {
                $context->markAsFailed($product, $exception);
            }
        }

        $deleteProductPriceStmts = [];
        $deleteProductPropertyStmts = [];
        $deleteProductVisibilityStmts = [];

        if ($productIds !== []) {
            // TODO optimize to delete on unused
            $deleteProductPriceStmts = \iterable_map(
                $this->dal->ids('product_price', (new Criteria())->addFilter(new EqualsAnyFilter('productId', $productIds))),
                static fn (string $id): array => ['id' => $id]
            );
            // TODO optimize to delete on unused
            $deleteProductPropertyStmts = \iterable_to_array(
                $this->dal->ids('product_property', (new Criteria())->addFilter(new EqualsAnyFilter('productId', $productIds)))
            );

            $deleteProductVisibilityIds = [];

            // Remove non visible sales channel in product visibility
            foreach ($productUpserts as $productIndex => $productUpsert) {
                if (!\array_key_exists('visibilities', $productUpsert)) {
                    continue;
                }

                foreach ($productUpsert['visibilities'] as $visibilityIndex => $visibility) {
                    if (!($visibility['visibility'] ?? false)) {
                        $deleteProductVisibilityIds[] = $visibility['id'];

                        unset($productUpserts[$productIndex]['visibilities'][$visibilityIndex]);
                    }
                }
            }

            if ($deleteProductVisibilityIds !== []) {
                $deleteProductVisibilityStmts = \iterable_map(
                    $this->dal->ids('product_visibility', new Criteria($deleteProductVisibilityIds)),
                    static fn (string $id): array => ['id' => $id]
                );
            }
        }

        $this->dal->createSyncer()
            ->delete('product_visibility', $deleteProductVisibilityStmts)
            ->delete('product_property', $deleteProductPropertyStmts)
            ->delete('product_price', $deleteProductPriceStmts)
            ->upsert('product', $productUpserts)
            ->flush();
    }
}
