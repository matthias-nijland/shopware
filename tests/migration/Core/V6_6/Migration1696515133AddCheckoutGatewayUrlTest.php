<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1696515133AddCheckoutGatewayUrl;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1696515133AddCheckoutGatewayUrl::class)]
#[Package('checkout')]
class Migration1696515133AddCheckoutGatewayUrlTest extends TestCase
{
    use KernelTestBehaviour;
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $urlColumn = $this->getColumnOfTable($this->connection, 'app', 'checkout_gateway_url');
        static::assertFalse($urlColumn->getNotnull());
    }

    private function migrate(): void
    {
        (new Migration1696515133AddCheckoutGatewayUrl())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `app` DROP COLUMN `checkout_gateway_url`');
    }
}
