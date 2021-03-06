<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Commands;

use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateThumbnailsCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var EntityRepository
     */
    private $mediaRepository;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var Filter | null
     */
    private $folderFilter;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepository;

    public function __construct(
        ThumbnailService $thumbnailService,
        EntityRepository $mediaRepository,
        EntityRepositoryInterface $mediaFolderRepository
    ) {
        parent::__construct();

        $this->thumbnailService = $thumbnailService;
        $this->mediaRepository = $mediaRepository;
        $this->mediaFolderRepository = $mediaFolderRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('media:generate-thumbnails')
            ->setDescription('Generates the thumbnails for media entities')
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_REQUIRED,
                'Number of entities per iteration',
                '50'
            )
            ->addOption(
                'folder-name',
                null,
                InputOption::VALUE_REQUIRED,
                'An optional folder name to create thumbnails'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $this->initializeCommand($input, $context);

        $mediaIterator = new RepositoryIterator($this->mediaRepository, $context, $this->createCriteria());

        $totalMediaCount = $mediaIterator->getTotal();
        $this->io->comment(sprintf('Generating Thumbnails for %d files. This may take some time...', $totalMediaCount));
        $this->io->progressStart($totalMediaCount);

        $result = $this->generateThumbnails($mediaIterator, $context);

        $this->io->progressFinish();
        $this->io->table(
            ['Action', 'Number of Media Entities'],
            [
                ['Generated', $result['generated']],
                ['Skipped', $result['skipped']],
            ]
        );
    }

    private function initializeCommand(InputInterface $input, Context $context)
    {
        $this->folderFilter = $this->getFolderFilterFromInput($input, $context);
        $this->batchSize = $this->getBatchSizeFromInput($input);
    }

    private function getBatchSizeFromInput(InputInterface $input): int
    {
        $rawInput = $input->getOption('batch-size');

        if (!is_numeric($rawInput)) {
            throw new \UnexpectedValueException('Batch size must be numeric');
        }

        return (int) $rawInput;
    }

    private function getFolderFilterFromInput(InputInterface $input, Context $context)
    {
        $rawInput = $input->getOption('folder-name');
        if (!$rawInput) {
            return null;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', $rawInput));

        $searchResult = $this->mediaFolderRepository->search($criteria, $context);

        if ($searchResult->getTotal() === 0) {
            throw new \UnexpectedValueException(
                sprintf(
                    'Could not find a folder with the name: "%s"',
                    $rawInput
                )
            );
        }

        return new EqualsAnyFilter('mediaFolderId', $searchResult->getIds());
    }

    private function generateThumbnails(RepositoryIterator $iterator, Context $context): array
    {
        $generated = 0;
        $skipped = 0;

        while (($result = $iterator->fetch()) !== null) {
            foreach ($result->getEntities() as $media) {
                if ($this->thumbnailService->updateThumbnails($media, $context) > 0) {
                    ++$generated;
                } else {
                    ++$skipped;
                }
            }
            $this->io->progressAdvance($result->count());
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
        ];
    }

    private function createCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit($this->batchSize);
        $criteria->addFilter(new EqualsFilter('media.mediaFolder.configuration.createThumbnails', true));

        if ($this->folderFilter) {
            $criteria->addFilter($this->folderFilter);
        }

        $criteria->addAssociation('media.mediaFolder');

        return $criteria;
    }
}
