<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1736866790AddDocumentA11yMediaFileIdForDocumentTable;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1736866790AddDocumentA11yMediaFileIdForDocumentTable::class)]
class Migration1736866790AddDocumentA11yMediaFileIdForDocumentTableTest extends TestCase
{
    use KernelTestBehaviour;
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        $this->rollback();

        $this->migrate();
        $this->migrate();

        static::assertTrue($this->hasForeignKey());
    }

    private function migrate(): void
    {
        (new Migration1736866790AddDocumentA11yMediaFileIdForDocumentTable())->update($this->connection);
    }

    private function rollback(): void
    {
        if ($this->hasForeignKey()) {
            $this->connection->executeStatement('ALTER TABLE `document` DROP FOREIGN KEY `fk.document.document_a11y_media_file_id`');
        }
    }

    private function hasForeignKey(): bool
    {
        $foreignKey = $this->getForeignKeyOfTable($this->connection, 'document', 'fk.document.document_a11y_media_file_id');

        return $foreignKey->getReferencedTableName()->getUnqualifiedName()->getValue() === 'media'
            && $foreignKey->getReferencingColumnNames()[0]->getIdentifier()->getValue() === 'document_a11y_media_file_id'
            && $foreignKey->getReferencedColumnNames()[0]->getIdentifier()->getValue() === 'id';
    }
}
