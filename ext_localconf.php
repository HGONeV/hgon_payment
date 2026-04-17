<?php

use HGON\HgonPayment\Payment\Paypal;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or die("Access denied.");

call_user_func(
    function($extKey)
    {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            $extKey,
            'Order',
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmPayment, executePayment, finishedPayment'
            ],
            // non-cacheable actions
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmPayment, executePayment, finishedPayment'
            ],
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            $extKey,
            'Subscription',
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmSubscription, executeSubscription, finishedSubscription'
            ],
            // non-cacheable actions
            [
                \HGON\HgonPayment\Controller\PayPalController::class => 'confirmSubscription, executeSubscription, finishedSubscription'
            ],
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
        );


        // caching
        $cacheConfigurations =& $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'];

        if (!isset($cacheConfigurations[$extKey]) || !is_array($cacheConfigurations[$extKey])) {
            $cacheConfigurations[$extKey] = [];
        }

        $cacheConfigurations[$extKey]['frontend'] ??= VariableFrontend::class;
        $cacheConfigurations[$extKey]['options'] ??= ['defaultLifetime' => 3600];
        $cacheConfigurations[$extKey]['groups'] ??= ['pages'];




    },
    'hgon_payment'
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sf_event_mgt']['paymentMethods']['paypal'] = [
    'class' => Paypal::class,
    'extkey' => 'hgon_payment',
];
unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['sf_event_mgt']['paymentMethods']['invoice']);

// set logger
$GLOBALS['TYPO3_CONF_VARS']['LOG']['HGON']['HgonPayment']['writerConfiguration'] = array(

    // configuration for WARNING severity, including all
    // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
    \TYPO3\CMS\Core\Log\LogLevel::WARNING => array(
        // add a FileWriter
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => array(
            // configuration for the writer
            'logFile' => 'var/log/tx_hgonpayment.log'
        )
    ),
);
