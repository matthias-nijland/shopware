<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\DbTableHelper;
use Shopware\Core\Migration\V6_7\Migration1763125891AddProductTypeColumn;

/**
 * @internal
 */
#[CoversClass(Migration1763125891AddProductTypeColumn::class)]
class Migration1763125891AddProductTypeColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdateAddsTypeColumnAndIndex(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        static::assertInstanceOf(MySQLSchemaManager::class, $schemaManager);
        $this->ensureStatesColumnExists($schemaManager);
        $this->dropTypeColumnIfExists($schemaManager);

        $migration = new Migration1763125891AddProductTypeColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $typeColumn = DbTableHelper::getColumnOfTable($schemaManager, 'product', 'type');
        static::assertSame('physical', $typeColumn->getDefault());
        static::assertTrue(DbTableHelper::indexExists($schemaManager, 'product', 'idx.product.type'));
    }

    private function dropTypeColumnIfExists(MySQLSchemaManager $schemaManager): void
    {
        if (DbTableHelper::indexExists($schemaManager, 'product', 'idx.product.type')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.type` ON `product`');
        }

        if (DbTableHelper::columnExists($schemaManager, 'product', 'type')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `type`');
        }
    }

    private function ensureStatesColumnExists(MySQLSchemaManager $schemaManager): void
    {
        if (DbTableHelper::columnExists($schemaManager, 'product', 'states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `states` JSON NULL');
    }
}
