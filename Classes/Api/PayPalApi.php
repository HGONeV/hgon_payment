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
     * @var \HGON\HgonTemplate\Domain\Model\PaymentProfile
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
    protected $cacheIdentifier = "hgon_template";

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
            $this->cUrl = curl_init();

            curl_setopt($this->cUrl, CURLOPT_POST, 1);
            curl_setopt($this->cUrl, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($this->cUrl, CURLOPT_URL, $this->host . '/v1/oauth2/token');
            curl_setopt($this->cUrl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($this->cUrl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($this->cUrl, CURLOPT_USERPWD, $clientId.":".$clientSecret);
            curl_setopt($this->cUrl, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Accept-Language: en_US'));

            $result = curl_exec($this->cUrl);
            if (!$result) {
                return "Connection Failure";
            } else {
                // put array with access_token, refresh_token etc into variable
                $this->clientCredentials = json_decode($result);
                $this->clientCredentials->expiresInTstamp = time() + $this->clientCredentials->expires_in;
                $this->cacheManager->set($this->cacheDataIdentifier, $this->clientCredentials);
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
     * @return \HGON\HgonTemplate\Domain\Model\PaymentProfile
     */
    protected function setCheckoutExperience ($brandName = 'HGON e.V - 5', $logoImageUrl = 'EXT:hgon_template/Resources/Public/Images/PayPal/logo-hgon.svg', $localeCode = 'DE', $allowNote = true, $noShipping = false, $addressOverride = true)
    {
        // Either: Check for existing profile
        /** @var \HGON\HgonTemplate\Domain\Repository\PaymentProfileRepository $paymentProfileRepository */
        $paymentProfileRepository = $this->objectManager->get('HGON\\HgonTemplate\\Domain\\Repository\\PaymentProfileRepository');
        $profile = $paymentProfileRepository->findByTitle('paypal')->getFirst();

        if ($profile instanceof \HGON\HgonTemplate\Domain\Model\PaymentProfile) {
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
        /** @var \HGON\HgonTemplate\Domain\Model\PaymentProfile $paymentProfile */
        $paymentProfile = $this->objectManager->get('HGON\\HgonTemplate\\Domain\\Model\\PaymentProfile');
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
     * @param float $amount
     * @param string $description
     * @param float $shippingCosts
     * @param boolean $valueAddedTax set to true, if $amount is gross
     *
     * @return \stdClass|boolean
     */
    public function createPayment($amount, $description, $shippingCosts = 0.00, $valueAddedTax = false)
    {
        $subtotal = number_format($amount, 2, '.', ',');
        $tax = '0.00';
        // if amount is gross
        if ($valueAddedTax) {
            $subtotal = ($amount / (100 + self::VAT)) * 100;
            $tax = $amount - $subtotal;
        }

        $data = [
           // 'experience_profile_id' => $this->paymentProfile->getProfileId(),
            'intent' => 'sale',
            'payer' => [
                'payment_method' => 'paypal'
            ],
            'transactions' => [
                [
                    'amount' => [
                        'total' => number_format($amount, 2, '.', ','),
                        'currency' => 'EUR',
                        'details' => [
                            'subtotal' => number_format($subtotal, 2, '.', ','),
                            'tax' => number_format($tax, 2, '.', ','),
                            'shipping' => number_format($shippingCosts, 2, '.', ',')
                        ]
                    ],
                    'description' => 'Spende für den Naturschutz. HGON sagt DANKE!',
                //    'custom' => 'This is a hidden value',
                    'invoice_number' => 'unique number',
                    'soft_descriptor' => 'Übersicht',
                    'item_list' => [
                        'items' => [
                            [
                                'name' => 'Spende 1',
                                'description' => 'Dies kommt der Umwelt zugute',
                                'quantity' => '1',
                                'price' => '10.00',
                                'sku' => 'Interne Projektbezeichnung',
                                'currency' => 'EUR'
                            ]
                        ]
                    ],
                ]
            ],
           // 'note_to_payer' => 'Haben Sie fragen? Melden Sie sich gerne bei uns!',
            'redirect_urls' => [
                'return_url' => 'http://hgon.rkw.local/entdecken/',
                'cancel_url' => 'http://hgon.rkw.local/mitmachen/'
            ]
        ];

        $url = $this->host . '/v1/payments/payment';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        $this->cUrl = $curl;
        return $this->sendRequest();
        //===
    }



    /**
     * checkStatus
     *
     * @return \stdClass|boolean
     */
    public function checkStatus()
    {
        $url = $this->host . '/v1/budget';
        $authorization = 'Authorization: Bearer ' . $this->clientCredentials->access_token;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $data = array(
            'additionalProducts' 		=> "RSV",
        );

        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        /*
        // $this->clientCredentials->values[0]->access_token

		$url = $this->host . '/1/terms';
        $auth_data = array(
            'client_id' 		=> $this->clientId,
            'client_secret' 	=> $this->clientSecret,
            'access_token' 		=> $this->clientCredentials->values[0]->access_token
        );
		curl_setopt($this->cUrl, CURLOPT_URL, $url);
        curl_setopt($this->cUrl, CURLOPT_POSTFIELDS, http_build_query($auth_data));

		return $this->sendRequest();
        */
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
        return Common::getTyposcriptConfiguration('Hgontemplate', $which);
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
     * @return \HGON\HgonTemplate\Domain\Model\PaymentProfile $paymentProfile
     */
    public function getPaymentProfile()
    {
        return $this->paymentProfile;
    }

    /**
     * Sets the paymentProfile
     *
     * @param \HGON\HgonTemplate\Domain\Model\PaymentProfile $paymentProfile
     * @return void
     */
    public function setPaymentProfile(\HGON\HgonTemplate\Domain\Model\PaymentProfile $paymentProfile)
    {
        $this->paymentProfile = $paymentProfile;
    }

}