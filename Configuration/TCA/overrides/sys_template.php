<?php
defined('TYPO3') or die("Access denied.");

//=================================================================
// Add TypoScript
//=================================================================
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'hgon_payment',
    'Configuration/TypoScript',
    'HGON Payment'
);
