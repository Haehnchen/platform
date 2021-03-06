<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use DateTime;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginLifecycleServiceTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour,
        PluginTestsHelper;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->container = $this->getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            $this->pluginRepo,
            $this->container->get('language.repository')
        );
        $this->kernel = $this->container->get('kernel');
        $this->connection = $this->container->get(Connection::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();
        require_once __DIR__ . '/_fixture/SwagTest/Migration/Migration1536761533Test.php';
        $this->addTestPluginToKernel();
        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        $this->connection->executeUpdate(
            sprintf(
                'DROP TABLE IF EXISTS `%s`',
                \SwagTest\Migration\Migration1536761533Test::TABLE_NAME
            )
        );
        $this->connection->executeUpdate(
            sprintf(
                'DELETE FROM `migration` WHERE `creation_timestamp` = %d',
                \SwagTest\Migration\Migration1536761533Test::TIMESTAMP
            )
        );
        $this->connection->executeUpdate(
            sprintf(
                "DELETE FROM `plugin` WHERE `name` = '%s'",
                \SwagTest\SwagTest::PLUGIN_NAME
            )
        );
    }

    public function testInstallPlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        self::assertTrue($this->pluginTableExists());
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $installedAt = (new DateTime())->format(Defaults::DATE_FORMAT);
        $this->createPlugin($this->pluginRepo, $this->context, \SwagTest\SwagTest::PLUGIN_VERSION, $installedAt);

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNotNull($pluginInstalled->getInstalledAt());
        self::assertSame($installedAt, $pluginInstalled->getInstalledAt()->format(Defaults::DATE_FORMAT));
    }

    public function testInstallPluginWithUpdate(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, \SwagTest\SwagTest::PLUGIN_OLD_VERSION);
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNotNull($pluginInstalled->getInstalledAt());
        self::assertNotNull($pluginInstalled->getUpgradedAt());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_VERSION, $pluginInstalled->getVersion());
    }

    public function testUninstallPlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);
        self::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->uninstallPlugin($pluginInstalled, $this->context);

        /** @var PluginEntity $pluginUninstalled */
        $pluginUninstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNull($pluginUninstalled->getInstalledAt());
        self::assertFalse($pluginUninstalled->getActive());
    }

    public function testUninstallPluginThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not installed at all');
        $this->pluginLifecycleService->uninstallPlugin($plugin, $this->context);
    }

    public function testUpdatePlugin(): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, \SwagTest\SwagTest::PLUGIN_OLD_VERSION);
        self::assertFalse($this->pluginTableExists());

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->updatePlugin($plugin, $this->context);

        /** @var PluginEntity $pluginUpdated */
        $pluginUpdated = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNotNull($pluginUpdated->getUpgradedAt());
        self::assertSame(\SwagTest\SwagTest::PLUGIN_VERSION, $pluginUpdated->getVersion());

        self::assertTrue($this->pluginTableExists());
    }

    public function testActivatePlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $this->context);

        /** @var PluginEntity $pluginActivated */
        $pluginActivated = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertTrue($pluginActivated->getActive());
    }

    public function testActivatePluginThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not installed at all');
        $this->pluginLifecycleService->activatePlugin($plugin, $this->context);
    }

    public function testDeactivatePlugin(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $this->context);

        /** @var PluginEntity $pluginActivated */
        $pluginActivated = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertTrue($pluginActivated->getActive());

        $this->pluginLifecycleService->deactivatePlugin($pluginActivated, $this->context);

        /** @var PluginEntity $pluginDeactivated */
        $pluginDeactivated = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertFalse($pluginDeactivated->getActive());
    }

    public function testDeactivatePluginNotInstalledThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not installed at all');
        $this->pluginLifecycleService->deactivatePlugin($plugin, $this->context);
    }

    public function testDeactivatePluginNotActivatedThrowsException(): void
    {
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        $this->pluginLifecycleService->installPlugin($plugin, $this->context);

        /** @var PluginEntity $pluginInstalled */
        $pluginInstalled = $this->pluginService->getPluginByName(\SwagTest\SwagTest::PLUGIN_NAME, $this->context);

        self::assertNotNull($pluginInstalled->getInstalledAt());

        $this->expectException(PluginNotActivatedException::class);
        $this->expectExceptionMessage('Plugin "SwagTest" is not activated at all');
        $this->pluginLifecycleService->deactivatePlugin($pluginInstalled, $this->context);
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->kernel,
            $this->container->get('service_container'),
            $this->container->get(MigrationCollection::class),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(MigrationRuntime::class),
            $this->connection
        );
    }

    private function addTestPluginToKernel(): void
    {
        require_once __DIR__ . '/_fixture/SwagTest/SwagTest.php';
        $this->kernel::getPlugins()->add(new \SwagTest\SwagTest(false));
    }

    private function pluginTableExists(): bool
    {
        $sql = <<<SQL
        SELECT count(*)
FROM information_schema.TABLES
WHERE table_schema = DATABASE() AND table_name = :tableName;
SQL;

        return (bool) $this->connection->fetchColumn(
            $sql,
            ['tableName' => \SwagTest\Migration\Migration1536761533Test::TABLE_NAME]
        );
    }
}
