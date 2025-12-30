<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1697112044PaymentAndShippingTechnicalNameRequired extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697112044;
    }

    public function update(Connection $connection): void
    {
        $manager = $connection->createSchemaManager();
        $tablePaymentMethod = $manager->introspectTableByUnquotedName(PaymentMethodDefinition::ENTITY_NAME);

        if ($tablePaymentMethod->hasColumn('technical_name') && !$tablePaymentMethod->getColumn('technical_name')->getNotnull()) {
            $connection->executeStatement('ALTER TABLE `payment_method` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }

        $tableShippingMethod = $manager->introspectTableByUnquotedName(ShippingMethodDefinition::ENTITY_NAME);

        if ($tableShippingMethod->hasColumn('technical_name') && !$tableShippingMethod->getColumn('technical_name')->getNotnull()) {
            $connection->executeStatement('ALTER TABLE `shipping_method` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }
    }
}
