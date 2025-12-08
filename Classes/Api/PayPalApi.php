<?php
namespace HGON\HgonPayment\Api;

use \RKW\RkwBasics\Helper\Common;
use \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Created by PhpStorm.
 * User: Maximilian Fäßler
 */
class PayPalApi
{
    /**
     * @var integer
     */
    const VAT = 19;

    /**
     * @var string
     */
    const VERSION = '2';

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
    protected $authorization;

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
     * @var \TYPO3\CMS\Core\Cache\CacheManager
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
     * Logger
     *
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger;

    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    /**
     * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObjectRenderer
     */
    public function injectContentObjectRenderer(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObjectRenderer): void {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     */
    public function injectConfigurationManager(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager): void {
        $this->configurationManager = $configurationManager;
    }

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager): void {
        $this->persistenceManager = $persistenceManager;
    }


    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->cObj = $this->contentObjectRenderer;

        $settings = $this->getSettings();

        if (version_compare(TYPO3_version, '9.5.0', '<=')) {
            $this->context = \TYPO3\CMS\Core\Utility\GeneralUtility::getApplicationContext();
        } else {
            $this->context = \TYPO3\CMS\Core\Core\Environment::getContext();
        }

        // live app or develop
        if ($settings['api']['paypal']['live']) {
            $this->host = $settings['api']['paypal']['apiUrl'];
            $this->clientId = $settings['api']['paypal']['clientId'];
            $this->clientSecret = $settings['api']['paypal']['clientSecret'];
        } else {
            $this->host = $settings['api']['paypal']['dev']['apiUrl'];
            $this->clientId = $settings['api']['paypal']['dev']['clientId'];
            $this->clientSecret = $settings['api']['paypal']['dev']['clientSecret'];
        }

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
                curl_setopt($this->cUrl, CURLOPT_USERPWD, $this->clientId.":".$this->clientSecret);
                curl_setopt($this->cUrl, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Accept-Language: en_US'));

                $result = curl_exec($this->cUrl);

                if (curl_error ($this->cUrl)) {
                    // log
                    $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to connect with paypal api. Error: %s.', curl_error ($this->cUrl)));
                }
                // put array with access_token, refresh_token etc into variable
                $this->clientCredentials = json_decode($result);
                $this->clientCredentials->expiresInTstamp = time() + $this->clientCredentials->expires_in;
                $this->cacheManager->set($this->cacheDataIdentifier, $this->clientCredentials);
            } catch (\Exception $e) {
                $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to connect with paypal api. Error: %s.', str_replace(array("\n", "\r"), '', $e->getMessage())));
            }
        }

        // check if we got any problem
        if ($this->clientCredentials->error == 'invalid_client') {

            // log
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to connect with paypal api. Error: %s.', str_replace(array("\n", "\r"), '', $this->clientCredentials->error_description)));

            // return error message
            return $this->clientCredentials;
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
        $paymentProfileRepository = $this->objectManager->get(\HGON\HgonPayment\Domain\Repository\PaymentProfileRepository::class);
        $profile = $paymentProfileRepository->findByTitle('paypal')->getFirst();

        if ($profile instanceof \HGON\HgonPayment\Domain\Model\PaymentProfile) {
            $this->setPaymentProfile($profile);
            return $profile;
        }

        // Or: Create new profile

        // @toDo: make it possible to set values via TS?
        // @toDo: use try-catch & Logger!

        // https://stackoverflow.com/questions/40038638/resize-image-in-custom-viewhelper
        $imageUri = '';
        try {
            $imageService = $this->objectManager->get(\TYPO3\CMS\Extbase\Service\ImageService::class);
            $image = $imageService->getImage($logoImageUrl, null, 0);
            $imageUri = $imageService->getImageUri($image, true);
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to catch the following image URI "%s". Please check the configuration. Error: %s', $logoImageUrl, $e->getMessage()));
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
        $paymentProfile = $this->objectManager->get(\HGON\HgonPayment\Domain\Model\PaymentProfile::class);
        $paymentProfile->setTitle('paypal');
        $paymentProfile->setDescription($brandName);
        $paymentProfile->setProfileId($result->id);
        $paymentProfileRepository->add($paymentProfile);

        $this->persistenceManager->persistAll();

        $this->setPaymentProfile($paymentProfile);
        return $paymentProfile;
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
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        /** @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder $uriBuilder */
        $uriBuilder = $objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
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
                    'description' => 'HGON sagt DANKE!',
                    //    'custom' => 'This is a hidden value',
                    'invoice_number' => $basket->getInvoiceNumber(),
                    'soft_descriptor' => 'Übersicht',
                    'item_list' => [
                        'items' => $basket->getArticleArrayForPayPal()
                    ],
                ]
            ],
            // 'note_to_payer' => 'Haben Sie fragen? Melden Sie sich gerne bei uns!',
            'redirect_urls' => [
                'return_url' => $returnUri,
                'cancel_url' => $settings['api']['cancelUrl']
            ]
        ];

        $url = $this->host . '/v1/payments/payment';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;
        $curl = curl_init();
        try {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
        }


        $this->cUrl = $curl;
        return $this->sendRequest();
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

        $curl = curl_init();
        try {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
        }

        $this->cUrl = $curl;
        return $this->sendRequest();
    }



    /**
     * createSubscription
     * for subscriptions / recurring paying e.g. donations
     *
     * @param \HGON\HgonPayment\Domain\Model\Article $article
     *
     * @return \stdClass|boolean
     */
    public function createSubscription(\HGON\HgonPayment\Domain\Model\Article $article)
    {
        $settings = $this->getSettings();

        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        /** @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder $uriBuilder */
        $uriBuilder = $objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
        $returnUri = $uriBuilder->reset()->setCreateAbsoluteUri(true)
            ->setTargetPageUid(intval($settings['subscriptionPid']))
            ->uriFor('confirmSubscription', null, 'PayPal', 'HgonPayment', 'Subscription');

        // the api does not accept return urls without ".de" at the end
        // because of this, the api throws error while testing. Use pseudo .de domain
        if (
            $this->context == "Development"
            //|| $this->context == "Production/Staging"
        ) {
            // override returnUri
            $returnUri = 'http://stage.hgon.de/mitmachen/hgon-sagt-danke/';
        }


        // get PayPalProduct by sku
        // -> create, if not exists
        $payPalProduct = $this->getPayPalProduct($article);

        // @toDo: get PayPayPlan by PayPalProduct (product_id)
        // -> create, if not exists
        // give Article: If it's a donation, this article will not be found in database table
        $payPalPlan = $this->getPayPalPlan($payPalProduct, $article);

        if (! ($payPalPlan instanceof \HGON\HgonPayment\Domain\Model\PayPalPlan)) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('Cannot fetch PayPalPlan for Article with UID "%s". Please check the configuration.', $article->getUid()));
            return false;
        }

        // active directyl with "SUBSCRIBE_NOW": https://developer.paypal.com/docs/platforms/subscriptions/#step-3-create-a-subscription
        // (Optional) Use the application_context/user_action field to automatically activate subscriptions. Set the field to SUBSCRIBE_NOW or send it empty. The default value is SUBSCRIBE_NOW. Otherwise, you need to make a POST v1/billing/subscriptions/{ID}/activate call to activate the subscription.

        // Create subscription by plan_id
        $data = [
            'plan_id' => $payPalPlan->getPlanId(),
            //'quantity' => 1,
            'application_context' => [
                'user_action' => 'SUBSCRIBE_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                // IMPORTANT: A local URI with .local to the end was NOT supported by the sandbox for testing!!
                'return_url' => $returnUri,
                'cancel_url' => $settings['api']['cancelUrl']
            ]
        ];

        $url = $this->host . '/v1/billing/subscriptions';
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;

        $curl = curl_init();
        try {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
        }

        $this->cUrl = $curl;
        return $this->sendRequest();
    }



    /**
     * getSubscription
     * get subscription data
     * https://developer.paypal.com/docs/api/subscriptions/v1/#subscriptions_get
     *
     * example from paypal:
     * https://api.sandbox.paypal.com/v1/billing/subscriptions/I-BW452GLLEP1G
     * own url:
     * https://api.sandbox.paypal.com/v1/billing/subscriptions/I-C0EYG31I8AKX
     *
     * @param string $subscriptionId
     *
     * @return \stdClass|boolean
     */
    public function getSubscription($subscriptionId)
    {
        $url = $this->host . '/v1/billing/subscriptions/' . $subscriptionId;
        $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;

        $curl = curl_init();
        try {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPGET, 1);
        } catch (\Exception $e) {
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
        }

        $this->cUrl = $curl;

        return $this->sendRequest();
    }


    /**
     * getPayPalProduct
     * get PayPalProduct from Repository
     * (create it, if necessary!)
     *
     * @param \HGON\HgonPayment\Domain\Model\Article $article
     *
     * @return \HGON\HgonPayment\Domain\Model\PayPalProduct
     */
    protected function getPayPalProduct(\HGON\HgonPayment\Domain\Model\Article $article)
    {
        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        /** @var \HGON\HgonPayment\Domain\Repository\PayPalProductRepository $payPalProductRepository */
        $payPalProductRepository = $objectManager->get(\HGON\HgonPayment\Domain\Repository\PayPalProductRepository::class);

        $payPalProduct = $payPalProductRepository->findBySku($article->getSku());
        if (! ($payPalProduct instanceof \HGON\HgonPayment\Domain\Model\PayPalProduct)) {
            // create it

            $data = [
                'name' => $article->getName(),
                'description' => $article->getDescription(),
                'type' => 'SERVICE',
                'category' => 'SERVICES'

            ];

            $url = $this->host . '/v1/catalogs/products';
            $authorization = 'Authorization: Bearer ' .  $this->clientCredentials->access_token;

            $curl = curl_init();
            try {
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            } catch (\Exception $e) {
                $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
            }

            $this->cUrl = $curl;
            $request = $this->sendRequest();

            // Create PayPalProduct
            /** @var \HGON\HgonPayment\Domain\Model\PayPalProduct $newPayPalProduct */
            $newPayPalProduct = $objectManager->get(\HGON\HgonPayment\Domain\Model\PayPalProduct::class);
            $newPayPalProduct->setName($request->name);
            $newPayPalProduct->setDescription($request->description);
            $newPayPalProduct->setType($request->type);
            $newPayPalProduct->setCategory($request->category);
            $newPayPalProduct->setSku($article->getSku());
            $newPayPalProduct->setProductId($request->id);

            $payPalProductRepository->add($newPayPalProduct);

            /** @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager */
            $persistenceManager = $objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
            $persistenceManager->persistAll();

            return $newPayPalProduct;
        }

        return $payPalProduct;
    }



    /**
     * getPayPalPlan
     * get PayPalPlan from Repository
     * (create it, if necessary!)
     *
     * @param \HGON\HgonPayment\Domain\Model\PayPalProduct $payPalProduct
     * @param \HGON\HgonPayment\Domain\Model\Article $article
     *
     * @return \HGON\HgonPayment\Domain\Model\PayPalPlan|bool
     */
    protected function getPayPalPlan(\HGON\HgonPayment\Domain\Model\PayPalProduct $payPalProduct, \HGON\HgonPayment\Domain\Model\Article $article = null)
    {
        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        /** @var \HGON\HgonPayment\Domain\Repository\PayPalPlanRepository $payPalPlanRepository */
        $payPalPlanRepository = $objectManager->get(\HGON\HgonPayment\Domain\Repository\PayPalPlanRepository::class);

        /** @var \HGON\HgonPayment\Domain\Model\PayPalPlan $payPalPlan */
        $payPalPlan = $payPalPlanRepository->findByProductId($payPalProduct->getProductId());
        if (! ($payPalPlan instanceof \HGON\HgonPayment\Domain\Model\PayPalPlan)) {

            // create it

            // if article is not set, try to get it by sku from database
            // if the article is a donation, there will be no article in database
            if (! ($article instanceof \HGON\HgonPayment\Domain\Model\Article)) {
                /** @var \HGON\HgonPayment\Domain\Repository\ArticleRepository $articleRepository */
                $articleRepository = $objectManager->get(\HGON\HgonPayment\Domain\Repository\ArticleRepository::class);
                /** @var \HGON\HgonPayment\Domain\Model\Article $article */
                $article = $articleRepository->findBySku($payPalProduct->getSku());
            }

            if (! ($article instanceof \HGON\HgonPayment\Domain\Model\Article)) {
                $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('Cannot find Article by SKU for creating a PayPalPlan for PayPalProduct "%s". Please check the configuration.', $payPalProduct->getProductId()));
                return false;
                //===
            }

            $data = [
                'product_id' => $payPalProduct->getProductId(),
                'name' => $payPalProduct->getName(),
                'description' => $payPalProduct->getDescription(),
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

            $curl = curl_init();
            try {
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=UTF-8', $authorization));
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            } catch (\Exception $e) {
                $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $url, $e->getMessage()));
            }

            $this->cUrl = $curl;
            $request = $this->sendRequest();

            // Create PayPalProduct
            /** @var \HGON\HgonPayment\Domain\Model\PayPalPlan $newPayPalPlan */
            $newPayPalPlan = $objectManager->get(\HGON\HgonPayment\Domain\Model\PayPalPlan::class);
            $newPayPalPlan->setTitle($request->name);
            $newPayPalPlan->setDescription($request->description);
            $newPayPalPlan->setPlanId($request->id);
            $newPayPalPlan->setStatus($request->status);

            $payPalPlanRepository->add($newPayPalPlan);

            /** @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager */
            $persistenceManager = $objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
            $persistenceManager->persistAll();

            return $newPayPalPlan;
        }

        return $payPalPlan;
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

            // log api internal error
            if (
                $result->name == "VALIDATION_ERROR"
                || $result->name == "INVALID_REQUEST"
            ) {
                $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An internal PayPal-API error occurs on field "%s" with message "%s". Please check the configuration.', $result->details[0]->field, $result->details[0]->issue));
            }
            return $result;
        }
        catch(\Exception $e){
            $this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::ERROR, sprintf('An error occurred while trying to make following api call "%s". Please check the configuration. Error: %s', $this->cUrl, $e->getMessage()));
            return FALSE;
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
    }



    /**
     * Returns logger instance
     *
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    private function getLogger()
    {
        if (!$this->logger instanceof \TYPO3\CMS\Core\Log\Logger) {
            $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        }

        return $this->logger;
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
