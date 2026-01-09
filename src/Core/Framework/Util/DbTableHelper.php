<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 *
 * @template TPlatform of AbstractPlatform
 */
#[Package('framework')]
readonly class DbTableHelper
{
    private function __construct()
    {
    }

    /**
     * @param AbstractSchemaManager<TPlatform> $schemaManager
     */
    public static function tableExists(AbstractSchemaManager $schemaManager, string $tableName): bool
    {
        return $schemaManager->tableExists($tableName);
    }

    /**
     * @param AbstractSchemaManager<TPlatform> $schemaManager
     * @param non-empty-string $table
     */
    public static function columnExists(AbstractSchemaManager $schemaManager, string $table, string $columnName): bool
    {
        return $schemaManager->introspectTable($table)->hasColumn($columnName);
    }

    /**
     * @param AbstractSchemaManager<TPlatform> $schemaManager
     * @param non-empty-string $table
     */
    public static function getColumnOfTable(AbstractSchemaManager $schemaManager, string $table, string $columnName): Column
    {
        return $schemaManager->introspectTable($table)->getColumn($columnName);
    }

    /**
     * @param AbstractSchemaManager<TPlatform> $schemaManager
     * @param non-empty-string $table
     */
    public static function indexExists(AbstractSchemaManager $schemaManager, string $table, string $indexName): bool
    {
        return $schemaManager->introspectTable($table)->hasIndex($indexName);
    }

    /**
     * @param AbstractSchemaManager<TPlatform> $schemaManager
     * @param non-empty-string $table
     */
    public static function getIndexOfTable(AbstractSchemaManager $schemaManager, string $table, string $indexName): Index
    {
        return $schemaManager->introspectTable($table)->getIndex($indexName);
    }

    /**
     * @param AbstractSchemaManager<TPlatform> $schemaManager
     * @param non-empty-string $table
     */
    public static function getForeignKeyOfTable(AbstractSchemaManager $schemaManager, string $table, string $foreignKeyName): ForeignKeyConstraint
    {
        return $schemaManager->introspectTable($table)->getForeignKey($foreignKeyName);
    }
}
