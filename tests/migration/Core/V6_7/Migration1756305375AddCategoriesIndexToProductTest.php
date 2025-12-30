<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1756305375AddCategoriesIndexToProduct;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1756305375AddCategoriesIndexToProduct::class)]
class Migration1756305375AddCategoriesIndexToProductTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testIndexIsCreated(): void
    {
        if ($this->indexExists($this->connection, 'product', 'idx.product.categories')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.categories` ON `product`');
        }

        $migration = new Migration1756305375AddCategoriesIndexToProduct();
        $migration->update($this->connection);

        static::assertTrue($this->indexExists($this->connection, 'product', 'idx.product.categories'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1756305375AddCategoriesIndexToProduct();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->indexExists($this->connection, 'product', 'idx.product.categories'));
    }
}
