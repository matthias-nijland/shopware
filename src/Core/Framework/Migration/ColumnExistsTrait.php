<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\DbTableHelper;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use {@see DbTableHelper::columnExists} instead
 *
 * @phpstan-ignore trait.unused (Trait is deprecated but might still be used by externals)
 */
trait ColumnExistsTrait
{
    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use DbTableHelper::columnExists instead')
        );

        return DbTableHelper::columnExists($connection->createSchemaManager(), $table, $column);
    }
}
