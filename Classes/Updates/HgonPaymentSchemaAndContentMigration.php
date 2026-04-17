<?php

declare(strict_types=1);

namespace HGON\HgonPayment\Updates;

use Doctrine\DBAL\ArrayParameterType;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Schema\Exception\DefaultTcaSchemaTablePositionException;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('hgonPaymentSchemaAndContentMigration')]
final class HgonPaymentSchemaAndContentMigration implements UpgradeWizardInterface, RepeatableInterface
{
    private const EXT_KEY = 'hgon_payment';

    public function getTitle(): string
    {
        return 'HGON Payment: Schema- und CType-Migration';
    }

    public function getDescription(): string
    {
        return 'Aktualisiert das Extension-Schema aus ext_tables.sql und migriert tt_content von list_type auf CType.';
    }

    public function executeUpdate(): bool
    {
        $statements = $this->getCreateTableStatements();
        if ($statements !== []) {
            $schemaMigrator = GeneralUtility::makeInstance(SchemaMigrator::class);
            try {
                $errors = $schemaMigrator->install($statements);
                if ($errors !== []) {
                    error_log(sprintf('[%s] Schema migration errors: %s', self::EXT_KEY, implode('; ', $errors)));
                }
            } catch (DefaultTcaSchemaTablePositionException $exception) {
                // Missing foreign table TCA (e.g. optional extensions) should not block list_type migration.
                error_log(sprintf(
                    '[%s] Schema migration skipped due to missing TCA table: %s',
                    self::EXT_KEY,
                    $exception->getMessage()
                ));
            }
        }

        $this->migrateListTypeToCType();
        return true;
    }

    public function updateNecessary(): bool
    {
        if ($this->hasListTypeRows()) {
            return true;
        }

        return false;
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    private function getCreateTableStatements(): array
    {
        $sqlFile = ExtensionManagementUtility::extPath(self::EXT_KEY, 'ext_tables.sql');
        if (!is_file($sqlFile)) {
            return [];
        }

        $sqlReader = GeneralUtility::makeInstance(SqlReader::class);
        return $sqlReader->getCreateTableStatementArray((string)file_get_contents($sqlFile));
    }

    private function migrateListTypeToCType(): void
    {
        $signatures = $this->getPluginSignatures();
        if ($signatures === []) {
            return;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tt_content');

        foreach ($signatures as $signature) {
            $connection->executeStatement(
                'UPDATE tt_content SET CType = :ctype, list_type = :listType WHERE CType = :oldCType AND list_type = :signature',
                [
                    'ctype' => $signature,
                    'listType' => '',
                    'oldCType' => 'list',
                    'signature' => $signature,
                ]
            );
        }
    }

    private function hasListTypeRows(): bool
    {
        $signatures = $this->getPluginSignatures();
        if ($signatures === []) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $count = $queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                $queryBuilder->expr()->in('list_type', $queryBuilder->createNamedParameter($signatures, ArrayParameterType::STRING))
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$count > 0;
    }

    private function getPluginSignatures(): array
    {
        $extensionName = str_replace(' ', '', ucwords(str_replace('_', ' ', self::EXT_KEY)));
        $pluginNames = [
            'Order',
            'Subscription',
        ];

        return array_map(
            static fn (string $pluginName): string => strtolower($extensionName . '_' . $pluginName),
            $pluginNames
        );
    }
}
