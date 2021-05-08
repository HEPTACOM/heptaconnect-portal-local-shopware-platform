<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Core\Storage\Contract\DenormalizerInterface;
use Heptacom\HeptaConnect\Core\Storage\NormalizationRegistry;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaUnpacker
{
    private MediaService $mediaService;

    private NormalizationRegistry $normalizationRegistry;

    private DalAccess $dalAccess;

    public function __construct(
        MediaService $mediaService,
        NormalizationRegistry $normalizationRegistry,
        DalAccess $dalAccess
    ) {
        $this->mediaService = $mediaService;
        $this->normalizationRegistry = $normalizationRegistry;
        $this->dalAccess = $dalAccess;
    }

    public function unpack(Media $source): array
    {
        if (!$this->dalAccess->idExists('media', $source->getPrimaryKey())) {
            $denormalizer = $this->normalizationRegistry->getDenormalizer('stream');

            if (!$denormalizer instanceof DenormalizerInterface) {
                // TODO error
                return [];
            }

            $stream = $denormalizer->denormalize($source->getNormalizedStream(), 'stream');

            if (!$stream instanceof StreamInterface) {
                // TODO error
                return [];
            }

            $mediaId = $this->mediaService->saveFile(
                $stream->getContents(),
                \explode('/', $source->getMimeType(), 2)[1] ?? 'bin',
                $source->getMimeType(),
                Uuid::randomHex(),
                $this->dalAccess->getContext(),
                'product',
                null,
                false
            );

            $source->setPrimaryKey($mediaId);
        }

        return [
            'id' => $source->getPrimaryKey(),
        ];
    }
}
