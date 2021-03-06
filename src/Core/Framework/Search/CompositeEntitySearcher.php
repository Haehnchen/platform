<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CompositeEntitySearcher
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $changesRepository;

    /**
     * @var SearchBuilder
     */
    private $searchBuilder;

    public function __construct(
        ContainerInterface $container,
        SearchBuilder $searchBuilder,
        EntityRepositoryInterface $changesRepository
    ) {
        $this->container = $container;
        $this->changesRepository = $changesRepository;
        $this->searchBuilder = $searchBuilder;
    }

    public function search(string $term, int $limit, Context $context, string $userId): array
    {
        $results = $this->searchEntities($term, $context);

        //apply audit log for each entity, which considers which data the user working with
        $results = $this->applyAuditLog($results, $userId, $context);

        $grouped = $this->sortEntities($limit, $results);

        $results = $this->fetchEntities($context, $grouped);

        return [
            'data' => array_values($results),
        ];
    }

    /**
     * @param IdSearchResult[] $results
     * @param string           $userId
     * @param Context          $context
     *
     * @return array
     */
    private function applyAuditLog(array $results, string $userId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('version_commit_data.entityName', [
                ProductDefinition::getEntityName(),
                OrderDefinition::getEntityName(),
                CustomerDefinition::getEntityName(),
            ])
        );
        $criteria->addFilter(
            new EqualsFilter('version_commit_data.userId', $userId)
        );

        $criteria->addSorting(new FieldSorting('version_commit_data.autoIncrement', 'DESC'));
        $criteria->setLimit(40);

        /** @var VersionCommitDataCollection $changes */
        $changes = $this->changesRepository->search($criteria, $context)->getEntities();

        $formatted = [];

        foreach ($results as $definition => $entities) {
            $definitionChanges = $changes->filterByEntity($definition);

            foreach ($entities->getData() as $key => $row) {
                $id = $row['primary_key'];

                $entityChanges = $definitionChanges->filterByEntityPrimary($definition, ['id' => $id]);

                $score = 1;

                if ($entityChanges->count() > 0) {
                    $score = 1 + ($entityChanges->count() / 10);
                } elseif ($definitionChanges->count() > 0) {
                    $score = 1.05;
                }

                $row['_score'] = $row['_score'] ?? 1;
                $row['_score'] *= $score;
                $row['definition'] = $definition;

                $formatted[] = $row;
            }
        }

        return $formatted;
    }

    private function fetchEntities(Context $context, array $grouped): array
    {
        $results = [];

        foreach ($grouped as $definition => $rows) {
            $definition = (string) $definition;

            /** @var EntityDefinition|string $definition */
            $name = $definition::getEntityName();

            $repository = $this->container->get($name . '.repository');
            if (!$repository instanceof EntityRepositoryInterface) {
                continue;
            }

            $criteria = new Criteria(\array_keys($rows));

            /** @var EntityCollection $entities */
            $entities = $repository->search($criteria, $context);

            foreach ($entities as $entity) {
                $score = (float) $rows[$entity->getId()];

                $entity->addExtension('search', new ArrayEntity(['_score' => $score]));

                $results[] = [
                    'type' => $name,
                    '_score' => $score,
                    'entity' => $entity,
                ];
            }
        }

        return $results;
    }

    private function sortEntities(int $limit, array $results): array
    {
        //create flat result to sort all elements descending by score
        \usort($results, function (array $a, array $b) {
            return $b['_score'] <=> $a['_score'];
        });

        //create internal paging for best matches
        $results = \array_slice($results, 0, $limit);

        //group best hits to send one read request per definition
        $grouped = [];
        foreach ($results as $row) {
            $definition = $row['definition'];

            $grouped[$definition][$row['primary_key']] = $row['_score'];
        }

        return $grouped;
    }

    private function searchEntities(string $term, Context $context): array
    {
        $definitions = [
            ProductDefinition::class,
            OrderDefinition::class,
            CustomerDefinition::class,
            MediaDefinition::class,
        ];

        $results = [];

        //fetch best matches for defined definitions
        foreach ($definitions as $definition) {
            /** @var string|EntityDefinition $definition */
            $criteria = new Criteria();
            $criteria->setLimit(15);

            $this->searchBuilder->build($criteria, $term, $definition, $context);

            /** @var EntityRepositoryInterface $repository */
            $repository = $this->container->get($definition::getEntityName() . '.repository');

            $results[$definition] = $repository->searchIds($criteria, $context);
        }

        return $results;
    }
}
