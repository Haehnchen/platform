<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MigrationController extends AbstractController
{
    /**
     * @var MigrationCollectionLoader
     */
    private $loader;

    /**
     * @var MigrationRuntime
     */
    private $runner;

    public function __construct(
        MigrationCollectionLoader $loader,
        MigrationRuntime $runner
    ) {
        $this->loader = $loader;
        $this->runner = $runner;
    }

    /**
     * @Route("/api/v{version}/_action/database/sync-migration", name="api.action.database.sync-migration", methods={"POST"})
     */
    public function syncMigrations(Request $request): JsonResponse
    {
        $this->loader->syncMigrationCollection($request->request->get('identifier', MigrationCollectionLoader::SHOPWARE_CORE_MIGRATION_IDENTIFIER));

        return new JsonResponse(['message' => 'migrations added to the database']);
    }

    /**
     * @Route("/api/v{version}/_action/database/migrate", name="api.action.database.migrate", methods={"POST"})
     */
    public function migrate(Request $request): JsonResponse
    {
        if (!($limit = $request->request->getInt('limit'))) {
            $limit = null;
        }

        if (!($until = $request->request->getInt('until'))) {
            $until = null;
        }

        $generator = $this->runner->migrate($until, $limit);

        return $this->migrateGenerator($generator);
    }

    /**
     * @Route("/api/v{version}/_action/database/migrate-destructive", name="api.action.database.migrate-destructive", methods={"POST"})
     */
    public function migrateDestructive(Request $request): JsonResponse
    {
        if (!($limit = $request->request->getInt('limit'))) {
            $limit = null;
        }

        if (!($until = $request->request->getInt('until'))) {
            $until = null;
        }

        $generator = $this->runner->migrateDestructive($until, $limit);

        return $this->migrateGenerator($generator);
    }

    private function migrateGenerator(\Generator $generator): JsonResponse
    {
        try {
            while ($generator->valid()) {
                $generator->next();
            }
        } catch (\Exception $e) {
            throw new MigrateException('Migration Error: "' . $e->getMessage() . '"');
        }

        return new JsonResponse(['message' => 'Migrations executed']);
    }
}
