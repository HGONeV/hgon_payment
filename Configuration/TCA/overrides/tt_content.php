<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function (string $extKey) {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
            $extKey,
            'Order',
            'HGON Payment: Bestell-Abwicklung'
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
            $extKey,
            'Subscription',
            'HGON Payment: Abo-Abwicklung'
        );

    },
    'hgon_payment'
);

