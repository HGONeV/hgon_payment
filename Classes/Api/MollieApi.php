<?php
namespace HGON\HgonPayment\Api;

use \RKW\RkwBasics\Helper\Common;
use \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Created by PhpStorm.
 * User: Maximilian Fäßler
 */
class MollieApi
{
    /**
     * @var integer
     */
    const VAT = 19;

    /**
     * @var string
     */
    const CURRENCY = 'EUR';

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $clientSecret;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $cUrl;

    /**
     * @var string
     */
    protected $clientCredentials;

    /**
     * @var string
     */
    protected $debug = true;

    /**
     * @var string
     */
    protected $context;

    /**
     * paymentProfile
     *
     * @var \HGON\HgonPayment\Domain\Model\PaymentProfile
     */
    protected $paymentProfile;

    /**
     * objectManager
     *
     * @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend
     */
    protected $objectManager;

    /**
     * cacheManager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $cacheManager;

    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    protected $cObj;

    /**
     * cacheIdentifier
     *
     * @var string
     */
    protected $cacheIdentifier = "hgon_payment";

    /**
     * cacheDataIdentifier
     *
     * @var string
     */
    protected $cacheDataIdentifier = "clientCredentials";

    /**
     * PersistenceManager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Logger
     *
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger;

    /**
     * Mollie
     *
     * @var \Mollie\Api\MollieApiClient
     */
    protected $mollie;


    private function initializeCache() {
        // initialize caching
        $this->cacheManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager')->getCache($this->cacheIdentifier);
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\Extbase\\Object\\ObjectManager');
        $configurationManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManager');
        $this->persistenceManager = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\PersistenceManager');
        $this->cObj = $configurationManager->getContentObject();
    }

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->initializeCache();

        $settings = $this->getSettings();

        if (version_compare(TYPO3_version, '9.5.0', '<=')) {
            $this->context = \TYPO3\CMS\Core\Utility\GeneralUtility::getApplicationContext();
        } else {
            $this->context = \TYPO3\CMS\Core\Core\Environment::getContext();
        }

        // if dev: overwrite credentials
        if ($settings['api']['mollie']['live']) {
            $this->clientId = $settings['api']['mollie']['clientId'];
            $this->clientSecret = $settings['api']['mollie']['clientSecret'];
        } else {
            $this->clientId = $settings['api']['mollie']['dev']['clientId'];
            $this->clientSecret = $settings['api']['mollie']['dev']['clientSecret'];
        }

        $this->mollie = $this->objectManager->get('Mollie\\Api\\MollieApiClient');
        $this->mollie->setApiKey($this->clientSecret);

    }



    /**
     * createCustomer
     * for subscriptions / recurring paying e.g. donations
     *
     * @param array $customer
     * @return \stdClass|boolean
     */
    public function createCustomer($customer)
    {
        try {
            $customer = $this->mollie->customers->create([
                "name" => filter_var($customer['name'], FILTER_SANITIZE_STRING),
                "email" => filter_var($customer['email'], FILTER_SANITIZE_STRING),
            ]);

            return $customer;
            //===
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to connect with mollie api createCustomer function. Error: %s.', str_replace(array("\n", "\r"), '', $e->getMessage())));
        }
        return false;
        //===
    }



    /**
     * createMandate
     * for subscriptions / recurring paying e.g. donations
     *
     * @param \stdClass $customer
     * @param array $formData
     * @return \stdClass|boolean
     */
    public function createMandate($customer, $formData)
    {
        try {
            $mandate = $this->mollie->mandates->createForId(
                $customer->id,
                [
                    "method" => \Mollie\Api\Types\MandateMethod::DIRECTDEBIT,
                    "consumerAccount" => filter_var(trim($formData['iban']), FILTER_SANITIZE_STRING),
                    "consumerName" => filter_var($formData['name'], FILTER_SANITIZE_STRING),
                ]
            );
            return $mandate;
            //===
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to connect with mollie api createMandate function. Error: %s.', str_replace(array("\n", "\r"), '', $e->getMessage())));
        }

        return false;
        //===
    }



    /**
     * createSubscription
     * for subscriptions / recurring paying e.g. donations
     *
     * @param \stdClass $customer
     * @param \HGON\HgonPayment\Domain\Model\Article $article
     *
     * @return \stdClass|boolean
     */
    public function createSubscription($customer, \HGON\HgonPayment\Domain\Model\Article $article)
    {
        try {
            $subscription = $this->mollie->subscriptions->createFor(
                $customer,
                [
                    "amount" => [
                        "currency" => "EUR",
                        "value" => number_format($article->getPrice(), 2)
                    ],
                    "description" => $article->getSku(),
                    "metadata" => [
                        "order_id" => time(),
                        "project" => $article->getName()
                    ],
                    "interval" => '1 month',
                    "startDate" => date('Y-m-d')
                ]
            );

            return $subscription;
            //===
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to connect with mollie api createSubscription function. Error: %s.', str_replace(array("\n", "\r"), '', $e->getMessage())));
        }

        return false;
        //===
    }


     /**
     * Returns TYPO3 settings
     *
     * @param string $which Which type of settings will be loaded
     * @return array
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    protected function getSettings($which = ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS)
    {
        return Common::getTyposcriptConfiguration('Hgonpayment', $which);
        //===
    }



    /**
     * Returns logger instance
     *
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    private function getLogger()
    {
        if (!$this->logger instanceof \TYPO3\CMS\Core\Log\Logger) {
            $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Log\\LogManager')->getLogger(__CLASS__);
        }

        return $this->logger;
        //===
    }

}