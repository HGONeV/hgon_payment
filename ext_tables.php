<?php
defined('TYPO3') or die("Access denied.");

call_user_func(
    function($extKey)
    {


        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_hgonpayment_domain_model_basket', 'EXT:hgon_payment/Resources/Private/Language/locallang_csh_tx_hgonpayment_domain_model_basket.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_hgonpayment_domain_model_basket');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_hgonpayment_domain_model_article', 'EXT:hgon_payment/Resources/Private/Language/locallang_csh_tx_hgonpayment_domain_model_article.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_hgonpayment_domain_model_article');

    },
    'hgon_donation'
);
