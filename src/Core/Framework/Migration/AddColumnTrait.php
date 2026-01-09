<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Util\DbTableHelper;

trait AddColumnTrait
{
    /**
     * @param non-empty-string $table
     *
     * @return bool true if the column was created, false if it already exists
     */
    protected function addColumn(
        Connection $connection,
        string $table,
        string $column,
        string $type,
        bool $nullable = true,
        string $default = 'NULL'
    ): bool {
        if (DbTableHelper::columnExists($connection->createSchemaManager(), $table, $column)) {
            return false;
        }

        // don't allow AFTER statements, it causes temporary tables which are extrem slow, because mysql has to copy whole tables
        $connection->executeStatement(
            'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $type . ' ' . ($nullable ? 'NULL' : 'NOT NULL') . ' DEFAULT ' . $default . ';'
        );

        return true;
    }
}
