<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536240668ProductCategory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536240668;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_category` (
              `product_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `category_id` binary(16) NOT NULL,
              `category_version_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`product_id`, `product_version_id`, `category_id`, `category_version_id`),
              CONSTRAINT `fk.product_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_category.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
