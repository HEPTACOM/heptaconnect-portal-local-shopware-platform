<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Base\File\FileReferenceContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Portal\Base\File\FileReferenceResolverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaUnpacker
{
    private MediaService $mediaService;

    private FileReferenceResolverContract $fileReferenceResolver;

    private DalAccess $dalAccess;

    private TranslatableUnpacker $translatableUnpacker;

    public function __construct(
        MediaService $mediaService,
        FileReferenceResolverContract $fileReferenceResolver,
        DalAccess $dalAccess,
        TranslatableUnpacker $translatableUnpacker
    ) {
        $this->mediaService = $mediaService;
        $this->fileReferenceResolver = $fileReferenceResolver;
        $this->dalAccess = $dalAccess;
        $this->translatableUnpacker = $translatableUnpacker;
    }

    public function unpack(Media $source): array
    {
        if (!$this->dalAccess->idExists('media', $source->getPrimaryKey())) {
            $mediaId = Uuid::randomHex();
            $fileReference = $source->getFile();

            if ($fileReference instanceof FileReferenceContract) {
                $file = $this->fileReferenceResolver->resolve($fileReference);
                $mediaId = $this->mediaService->saveFile(
                    $file->getContents(),
                    \explode('/', $source->getMimeType(), 2)[1] ?? 'bin',
                    $source->getMimeType(),
                    $mediaId,
                    $this->dalAccess->getContext(),
                    'product',
                    null,
                    false
                );
            }

            $source->setPrimaryKey($mediaId);
        }

        return [
            'id' => $source->getPrimaryKey(),
            'translations' => $this->unpackTranslations($source),
        ];
    }

    private function unpackTranslations(Media $media): array
    {
        return \array_merge_recursive(
            [],
            $this->translatableUnpacker->unpack($media->getTitle(), 'alt'),
            $this->translatableUnpacker->unpack($media->getTitle(), 'title'),
        );
    }
}
