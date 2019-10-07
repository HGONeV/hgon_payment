<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('hgon_payment', 'Configuration/TypoScript', 'HGON Payment');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_hgonpayment_domain_model_order', 'EXT:hgon_payment/Resources/Private/Language/locallang_csh_tx_hgonpayment_domain_model_order.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_hgonpayment_domain_model_order');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_hgonpayment_domain_model_article', 'EXT:hgon_payment/Resources/Private/Language/locallang_csh_tx_hgonpayment_domain_model_article.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_hgonpayment_domain_model_article');

    }
);
