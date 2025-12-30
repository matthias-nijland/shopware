<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
trait MigrationTestTrait
{
    private ?MySQLSchemaManager $schemaManager = null;

    #[Before]
    public function startTransaction(): void
    {
        KernelLifecycleManager::getConnection()->beginTransaction();
    }

    #[After]
    public function rollbackTransaction(): void
    {
        if (!KernelLifecycleManager::getConnection()->isTransactionActive()) {
            KernelLifecycleManager::getConnection()->rollBack();
        }
    }

    protected function fetchLanguageId(Connection $connection, string $code): ?string
    {
        return $connection->fetchOne(
            'SELECT `language`.`id`
             FROM `language`
                 INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
             WHERE `locale`.`code` = :code
             ORDER BY `language`.`created_at` ASC
             LIMIT 1',
            ['code' => $code]
        ) ?: null;
    }

    /**
     * @param non-empty-string $table
     */
    protected function columnExists(Connection $connection, string $table, string $columnName): bool
    {
        return $this->getSchemaManager($connection)->introspectTableByUnquotedName($table)->hasColumn($columnName);
    }

    /**
     * @param non-empty-string $table
     */
    protected function getColumnOfTable(Connection $connection, string $table, string $columnName): Column
    {
        return $this->getSchemaManager($connection)->introspectTableByUnquotedName($table)->getColumn($columnName);
    }

    /**
     * @param non-empty-string $table
     */
    protected function indexExists(Connection $connection, string $table, string $indexName): bool
    {
        return $this->getSchemaManager($connection)->introspectTableByUnquotedName($table)->hasIndex($indexName);
    }

    /**
     * @param non-empty-string $table
     */
    protected function getIndexOfTable(Connection $connection, string $table, string $indexName): Index
    {
        return $this->getSchemaManager($connection)->introspectTableByUnquotedName($table)->getIndex($indexName);
    }

    /**
     * @param non-empty-string $table
     */
    protected function getForeignKeyOfTable(Connection $connection, string $table, string $foreignKeyName): ForeignKeyConstraint
    {
        return $this->getSchemaManager($connection)->introspectTableByUnquotedName($table)->getForeignKey($foreignKeyName);
    }

    protected function getSchemaManager(Connection $connection): MySQLSchemaManager
    {
        if ($this->schemaManager === null) {
            $schemaManager = $connection->createSchemaManager();
            static::assertInstanceOf(MySQLSchemaManager::class, $schemaManager);
            $this->schemaManager = $schemaManager;
        }

        return $this->schemaManager;
    }
}
