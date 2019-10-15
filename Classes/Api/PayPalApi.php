<?php
namespace HGON\HgonPayment\Api;

use \RKW\RkwBasics\Helper\Common;
use \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Created by PhpStorm.
 * User: Maximilian Fäßler
 * Date: 15.01.2018
 */
class PayPalApi
{
    /**
     * integer
     */
    const VAT = 19;

    const VERSION = '2';

    protected $clientId;
    protected $clientSecret;
    protected $authorization;
    //protected $hostToken = 'https://api.sandbox.paypal.com/v1/oauth2/token';
    protected $host = 'https://api.sandbox.paypal.com';
    protected $cUrl;

    protected $clientCredentials;

    protected $debug = true;

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

        $this->clientId = $settings['api']['paypal']['clientId'];
        $this->clientSecret = $settings['api']['paypal']['clientSecret'];
        $clientId = $settings['api']['paypal']['clientId'];
        $clientSecret = $settings['api']['paypal']['clientSecret'];

        if (
            false &&
            $this->cacheManager->has($this->cacheDataIdentifier)
            && isset($this->clientCredentials->expiresInTstamp)
            && $this->clientCredentials->expiresInTstamp < time()
        ) {
            // read from cache
            $this->clientCredentials = $this->cacheManager->get($this->cacheDataIdentifier);


        } else {
            // make an API call
            try {
                $this->cUrl = curl_init();

                curl_setopt($this->cUrl, CURLOPT_POST, 1);
                curl_setopt($this->cUrl, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                curl_setopt($this->cUrl, CURLOPT_URL, $this->host . '/v1/oauth2/token');
                curl_setopt($this->cUrl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($this->cUrl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($this->cUrl, CURLOPT_USERPWD, $clientId.":".$clientSecret);
                curl_setopt($this->cUrl, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Accept-Language: en_US'));

                $result = curl_exec($this->cUrl);

                DebuggerUtility::var_dump($result); exit;

                // put array with access_token, refresh_token etc into variable
                $this->clientCredentials = json_decode($result);
                $this->clientCredentials->expiresInTstamp = time() + $this->clientCredentials->expires_in;
                $this->cacheManager->set($this->cacheDataIdentifier, $this->clientCredentials);
            } catch (\Exception $e) {
                $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::WARNING, sprintf('An error occurred while trying to connect with paypal api. Error: %s.', str_replace(array("\n", "\r"), '', $e->getMessage())));
            }

        }
        // create paypal profile, if not already existing
        $this->setCheckoutExperience();
    }



    /**
     * setCheckoutExperience
     *
     * -> The Checkout experience allows a merchant to create a profile where default parameters can be set and it allows designing
     * the overall experience for a consumer during checkout.
     * -> Experience profiles must not be generated with each transaction. They should be set once and then referenced.
     * (https://www.paypalobjects.com/webstatic/de_DE/downloads/PayPal-PLUS-IntegrationGuide.pdf)
     *
     * @param string $brandName
     * @param string $logoImageUrl
     * @param string $localeCode
     * @param boolean $allowNote
     * @param boolean $noShipping
     * @param boolean $addressOverride
     *
     * @return \HGON\HgonPayment\Domain\Model\PaymentProfile
     */
    protected function setCheckoutExperience ($brandName = 'HGON e.V - 5', $logoImageUrl = 'EXT:hgon_template/Resources/Public/Images/PayPal/logo-hgon.svg', $localeCode = 'DE', $allowNote = true, $noShipping = false, $addressOverride = true)
    {
        // Either: Check for existing profile
        /** @var \HGON\HgonPayment\Domain\Repository\PaymentProfileRepository $paymentProfileRepository */
        $paymentProfileRepository = $this->objectManager->get('HGON\\HgonPayment\\Domain\\Repository\\PaymentProfileRepository');
        $profile = $paymentProfileRepository->findByTitle('paypal')->getFirst();

        if ($profile instanceof \HGON\HgonPayment\Domain\Model\PaymentProfile) {
            $this->setPaymentProfile($profile);
            return $profile;
            //===
        }

        // Or: Create new profile

        // @toDo: make it possible to set values via TS?
        // @toDo: use try-catch & Logger!

        // https://stackoverflow.com/questions/40038638/resize-image-in-custom-viewhelper
        $imageUri = '';
        try {
            $imageService = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Service\\ImageService');
            $image = $imageService->getImage($logoImageUrl, null, 0);
            $imageUri = $imageService->getImageUri($image, true);
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::WARNING, sprintf('An error occurred while trying to catch the following image URI "%s". Please check the configuration. Error: %s', $logoImageUrl, $e->getMessage()));
        }

        $data = [
            'name' => $brandName,
            'temporary' => true,
            /*
            'presentation' => [
                'logo_image' => $imageUri
            ],
            'input_fields' => [
                'no_shipping' => $noShipping,
                'address_override' => $addressOverride
            ]
            */
        ];
        /*
            'locale_code' => $localeCode,
            'allow_note' => $allowNote,
         */

        $url = $this->host . '/v1/payment-experience/web-profiles';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $this->cUrl = $curl;
        $result = $this->sendRequest();
        curl_close($curl);

        // this profile should only created once. Save result (experience_profile_id) in DB!
        /** @var \HGON\HgonPayment\Domain\Model\PaymentProfile $paymentProfile */
        $paymentProfile = $this->objectManager->get('HGON\\HgonPayment\\Domain\\Model\\PaymentProfile');
        $paymentProfile->setTitle('paypal');
        $paymentProfile->setDescription($brandName);
        $paymentProfile->setProfileId($result->id);
        $paymentProfileRepository->add($paymentProfile);

        $this->persistenceManager->persistAll();



        $this->setPaymentProfile($paymentProfile);
        return $paymentProfile;
        //===
    }



    /**
     * getWebProfileList
     * shows the last 20 used profiles
     *
     * @return \stdClass|boolean
     */
    public function getWebProfileList()
    {
        $url = $this->host . '/v1/payment-experience/web-profiles';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);

        $this->cUrl = $curl;
        return $this->sendRequest();
        //===
    }



    /**
     * createPayment
     *
     * @param \HGON\HgonPayment\Domain\Model\Basket $basket
     *
     * @return \stdClass|boolean
     */
    public function createPayment(\HGON\HgonPayment\Domain\Model\Basket $basket)
    {
        $settings = $this->getSettings();

        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        /** @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder $uriBuilder */
        $uriBuilder = $objectManager->get('TYPO3\\CMS\\Extbase\\Mvc\\Web\\Routing\\UriBuilder');
        $returnUri = $uriBuilder->reset()->setCreateAbsoluteUri(true)
            ->setTargetPageUid(intval($settings['orderPid']))
            ->uriFor('confirmPayment', null, 'PayPal', 'HgonPayment', 'Order');

        $data = [
            // @toDo: Set Payment Profile
            // 'experience_profile_id' => $this->paymentProfile->getProfileId(),
            'intent' => 'sale',
            'payer' => [
                'payment_method' => 'paypal'
            ],
            'transactions' => [
                [
                    'amount' => [
                        'total' => $basket->getTotal(),
                        'currency' => 'EUR',
                        'details' => [
                            'subtotal' => $basket->getSubTotal(),
                            'tax' => $basket->getTaxTotal(),
                            'shipping' => $basket->getShippingCosts()
                        ]
                    ],
                    'description' => 'Spende für den Naturschutz. HGON sagt DANKE!',
                    //    'custom' => 'This is a hidden value',
                    'invoice_number' => $basket->getInvoiceNumber(),
                    'soft_descriptor' => 'Übersicht',
                    'item_list' => [
                        'items' => $basket->getArticleArrayForPayPal()
                    ],
                    /*
                    "shipping_address": {
                        "recipient_name": "Betsy customer",
                        "line1": "111 First Street",
                        "city": "Saratoga",
                        "country_code": "US",
                        "postal_code": "95070",
                        "phone": "0116519999164",
                        "state": "CA"
                      }
                     */
                ]
            ],
            // 'note_to_payer' => 'Haben Sie fragen? Melden Sie sich gerne bei uns!',
            'redirect_urls' => [
                'return_url' => $returnUri,
                'cancel_url' => 'http://hgon.rkw.local/mitmachen/'
            ]
        ];

        $url = $this->host . '/v1/payments/payment';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;

        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::WARNING, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
        }


        $this->cUrl = $curl;
        return $this->sendRequest();
        //===
    }



    /**
     * executePayment
     *
     * @param string $paymentId
     * @param string $token
     * @param string $payerId
     *
     * @return \stdClass|boolean
     */
    public function executePayment($paymentId, $token, $payerId)
    {
        $data = [
            'payer_id' => $payerId
        ];

        $url = $this->host . '/v1/payments/payment/' . $paymentId . '/execute';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;

        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::WARNING, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
        }

        $this->cUrl = $curl;
        return $this->sendRequest();
        //===
    }



    /**
     * createPlan
     * for subscriptions / recurring paying e.g. donations
     *
     * @param \HGON\HgonPayment\Domain\Model\Article $article
     *
     * @return \stdClass|boolean
     */
    public function createPlan(\HGON\HgonPayment\Domain\Model\Article $article)
    {
        $data = [
            //'product_id' => $article->getSku(),
            'product_id' => 'PROD-testlalala',
            'name' => $article->getName(),
            'description' => $article->getDescription(),
            'status' => 'ACTIVE',
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => 'MONTH',
                        'interval_count' => 1,
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => 0,
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => $article->getPrice(),
                            'currency_code' => 'EUR'
                        ]
                    ]
                ]
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'value' => '0',
                    'currency_code' => 'EUR'
                ],
                'setup_fee_failure_action' => "CONTINUE",
                'payment_failure_threshold' => 3
            ],
            /*
            'taxes' => [
                'percentage' => '0',
                'inclusive' => false
            ]
            */
        ];

        $url = $this->host . '/v1/billing/plans';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;

        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::WARNING, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
        }

        $this->cUrl = $curl;
        return $this->sendRequest();
        //===
    }



    /**
     * sendRequest
     *
     * @return \stdClass|boolean
     */
    protected function sendRequest()
    {
        try{
            $result = json_decode(curl_exec($this->cUrl));
            // format messages from stdclass to array
            if (property_exists($result, 'messages')) {
                $result->messages = (array)$result->messages;
            }
            return $result;
            //===
        }
        catch(\Exception $e){
            return FALSE;
            //===
        }
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
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Log\\LogManager')->getLogger(__CLASS__);
        //===
    }

    /**
     * Returns the paymentProfile
     *
     * @return \HGON\HgonPayment\Domain\Model\PaymentProfile $paymentProfile
     */
    public function getPaymentProfile()
    {
        return $this->paymentProfile;
    }

    /**
     * Sets the paymentProfile
     *
     * @param \HGON\HgonPayment\Domain\Model\PaymentProfile $paymentProfile
     * @return void
     */
    public function setPaymentProfile(\HGON\HgonPayment\Domain\Model\PaymentProfile $paymentProfile)
    {
        $this->paymentProfile = $paymentProfile;
    }

}