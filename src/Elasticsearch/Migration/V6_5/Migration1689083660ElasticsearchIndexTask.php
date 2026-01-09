<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\DbTableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1689083660ElasticsearchIndexTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1689083660;
    }

    public function update(Connection $connection): void
    {
        if (DbTableHelper::tableExists($connection->createSchemaManager(), 'elasticsearch_index_task')) {
            return;
        }

        $connection->executeStatement('
CREATE TABLE `elasticsearch_index_task` (
  `id` binary(16) NOT NULL,
  `index` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_count` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
