<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Base\File\FileReferenceContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Portal\Base\File\FileReferenceResolverContract;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\MimeTypes;

class MediaReceiver extends ReceiverContract
{
    private DalAccess $dal;

    private FileReferenceResolverContract $fileReferenceResolver;

    private FileSaver $fileSaver;

    public function __construct(
        DalAccess $dal,
        FileReferenceResolverContract $fileReferenceResolver,
        FileSaver $fileSaver
    ) {
        $this->dal = $dal;
        $this->fileReferenceResolver = $fileReferenceResolver;
        $this->fileSaver = $fileSaver;
    }

    public function supports(): string
    {
        return Media::class;
    }

    /**
     * @param Media $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $swContext = $this->dal->getContext();
        $mediaRepository = $this->dal->repository('media');
        $mediaFolderRepository = $this->dal->repository('media_folder');

        if (!$this->dal->idExists('media', $entity->getPrimaryKey())) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('defaultFolder.entity', 'import_export_profile'));
            $criteria->setLimit(1);
            $folderId = $mediaFolderRepository->searchIds($criteria, $swContext)->firstId();

            $entity->setPrimaryKey(PrimaryKeyGenerator::generatePrimaryKey($entity, 'c62caef4-4b51-4370-9e89-de9ba41faa54') ?? Uuid::randomHex());
            $mediaRepository->create([[
                'id' => $entity->getPrimaryKey(),
                'mediaFolderId' => $folderId,
            ]], $swContext);
        }

        $mediaRepository->update([[
            'id' => $entity->getPrimaryKey(),
            // TODO improve translations
            'alt' => $entity->getTitle()->getFallback(),
            'title' => $entity->getTitle()->getFallback(),
        ]], $swContext);

        $fileReference = $entity->getFile();

        if ($fileReference instanceof FileReferenceContract) {
            $file = $this->fileReferenceResolver->resolve($fileReference);
            $fileName = \tempnam(\sys_get_temp_dir(), 'local-media-receiver');

            try {
                \file_put_contents($fileName, $file->getContents());
                $swMediaFile = new MediaFile(
                    $fileName,
                    $entity->getMimeType(),
                    MimeTypes::getDefault()->getExtensions($entity->getMimeType())[0] ?? 'bin',
                    \filesize($fileName)
                );
                $this->fileSaver->persistFileToMedia($swMediaFile, $entity->getPrimaryKey(), $entity->getPrimaryKey(), $swContext);
            } finally {
                if (@\is_file($fileName)) {
                    @\unlink($fileName);
                }
            }
        }
    }
}
