<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\TextType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1742563555AddNotificationTable;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1742563555AddNotificationTable::class)]
class Migration1742563555AddNotificationTableTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `notification`;');
    }

    public function testMigration(): void
    {
        static::assertFalse($this->getSchemaManager($this->connection)->tableExists('notification'));

        $migration = new Migration1742563555AddNotificationTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->getSchemaManager($this->connection)->tableExists('notification'));

        $messageColumn = $this->getColumnOfTable($this->connection, 'notification', 'message');
        static::assertInstanceOf(TextType::class, $messageColumn->getType());
    }
}
