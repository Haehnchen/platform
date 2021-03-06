<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationCommand extends Command
{
    /**
     * @var MigrationCollectionLoader
     */
    protected $loader;

    /**
     * @var MigrationRuntime
     */
    protected $runner;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    public function __construct(
        MigrationCollectionLoader $loader,
        MigrationRuntime $runner
    ) {
        parent::__construct();

        $this->loader = $loader;
        $this->runner = $runner;
    }

    protected function getMigrationCommandName(): string
    {
        return 'database:migrate';
    }

    protected function getMigrationGenerator(?int $until, ?int $limit): \Generator
    {
        yield from $this->runner->migrate($until, $limit);
    }

    protected function getMigrationsCount(?int $until, ?int $limit)
    {
        return \count($this->runner->getExecutableMigrations($until, $limit));
    }

    protected function configure()
    {
        $this->setName($this->getMigrationCommandName())
            ->addArgument('identifier', InputArgument::OPTIONAL, 'identifier to determine which migrations to run', MigrationCollectionLoader::SHOPWARE_CORE_MIGRATION_IDENTIFIER)
            ->addArgument('until', InputArgument::OPTIONAL, 'timestamp cap for migrations')
            ->addOption('all', 'all', InputOption::VALUE_NONE, 'no migration timestamp cap')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getArgument('until') && !$input->getOption('all')) {
            throw new \InvalidArgumentException('missing timestamp cap or --all option');
        }

        $this->io = new SymfonyStyle($input, $output);

        $this->io->writeln('Get collection from directories');

        $this->loader->syncMigrationCollection($input->getArgument('identifier'));

        $this->io->writeln('migrate Migrations');

        $until = (int) $input->getArgument('until');
        $limit = (int) $input->getOption('limit');

        if ($input->getOption('all')) {
            $until = null;
        }

        $total = $this->getMigrationsCount($until, $limit);
        $this->io->progressStart($total);
        $migratedCounter = 0;

        try {
            foreach ($this->getMigrationGenerator($until, $limit) as $key => $return) {
                $this->io->progressAdvance();
                ++$migratedCounter;
            }
        } catch (\Exception $e) {
            $this->finishProgress($migratedCounter, $total);
            throw new MigrateException('Migration Error: "' . $e->getMessage() . '"');
        }

        $this->finishProgress($migratedCounter, $total);
        $this->io->writeln('all migrations executed');
    }

    private function finishProgress(int $migrated, int $total): void
    {
        if ($migrated === $total) {
            $this->io->progressFinish();
        }

        $this->io->table(
            ['Action', 'Number of migrations'],
            [
                ['Migrated', $migrated . ' from ' . $total],
            ]
        );
    }
}
