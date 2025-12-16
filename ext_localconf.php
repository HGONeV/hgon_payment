<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function($extKey)
    {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'HGON.HgonPayment',
            'Order',
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmPayment, executePayment, finishedPayment'
            ],
            // non-cacheable actions
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmPayment, executePayment, finishedPayment'
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'HGON.HgonPayment',
            'Subscription',
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmSubscription, executeSubscription, finishedSubscription'
            ],
            // non-cacheable actions
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmSubscription, executeSubscription, finishedSubscription'
            ]
        );


        // caching
        if( !is_array($GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey] ) ) {
            $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey] = array();
        }
        if( !isset($GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['frontend'] ) ) {
            $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
        }
        if( !isset($GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['options'] ) ) {
            $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['options'] = array('defaultLifetime' => 3600);
        }
        if( !isset($GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['groups'] ) ) {
            $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['groups'] = array('pages');
        }




    },
    'hgon_payment'
);

// set logger
$GLOBALS['TYPO3_CONF_VARS']['LOG']['HGON']['HgonPayment']['writerConfiguration'] = array(

    // configuration for WARNING severity, including all
    // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
    \TYPO3\CMS\Core\Log\LogLevel::WARNING => array(
        // add a FileWriter
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => array(
            // configuration for the writer
            'logFile' => 'typo3temp/logs/tx_hgonpayment.log'
        )
    ),
);
