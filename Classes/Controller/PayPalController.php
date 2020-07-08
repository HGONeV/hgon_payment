<?phpnamespace HGON\HgonPayment\Controller;/*** * * This file is part of the "HGON Donation" Extension for TYPO3 CMS. * * For the full copyright and license information, please read the * LICENSE.txt file that was distributed with this source code. * *  (c) 2018 Maximilian Fäßler <maximilian@faesslerweb.de>, Fäßler Web UG * ***/use HGON\HgonDonation\Helper\Donation as DonationHelper;use TYPO3\CMS\Extbase\Utility\DebuggerUtility;use TYPO3\CMS\Core\Utility\GeneralUtility;/** * PayPalController */class PayPalController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController{    /**     * authorsRepository     *     * @var \RKW\RkwAuthors\Domain\Repository\AuthorsRepository     * @inject     */    protected $authorsRepository = null;    /**     * @var \RKW\RkwRegistration\Domain\Repository\BackendUserRepository     * @inject     */    protected $backendUserRepository;    /**     * action confirmPaymentAction     * coming back from paypal with payment authorization     *     * @return void     */    public function confirmPaymentAction()    {        if (            \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('paymentId')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('token')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PayerID')            && $GLOBALS['TSFE']->fe_user->getKey('ses', 'hgon_payment_basket')        ) {            /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */            $basket = $GLOBALS['TSFE']->fe_user->getKey('ses', 'hgon_payment_basket');            $basket->setPaymentData([                'paymentId' => preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('paymentId')),                'token'     => preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('token')),                'payerId'   => preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PayerID'))            ]);            $GLOBALS['TSFE']->fe_user->setKey('ses', 'hgon_payment_basket', $basket);            $GLOBALS['TSFE']->storeSessionData();            $this->forward('executePayment');            //===            // diese Zwischenseite müsste nur genutzt werden, wenn sie zur finalen Bestellbestätigung dient            //    $this->view->assign('basket', $basket);        }        // if this data set - but we do not have some data in session - show error in view        if (            \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('paymentId')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('token')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PayerID')        ) {            $this->view->assign('showMessage', true);        }    }    /**     * action executePaymentAction     * action for executing donation money (PayPal)     *     * @return void     */    public function executePaymentAction()    {        /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */        $basket = $GLOBALS['TSFE']->fe_user->getKey('ses','hgon_payment_basket');        $paymentDataArray = $basket->getPaymentData();        /** @var \HGON\HgonPayment\Api\PayPalApi $payPalApi */        $payPalApi = $this->objectManager->get('HGON\\HgonPayment\\Api\\PayPalApi');        $result = $payPalApi->executePayment($paymentDataArray['paymentId'], $paymentDataArray['token'], $paymentDataArray['payerId']);        // $this->view->assign('basket', $basket);        //    DebuggerUtility::var_dump($basket);        //    DebuggerUtility::var_dump($result); exit;        // @toDo: Save Order        // Mailing        if ($result->state == "approved") {            $this->sendMails($result, $basket);        }        $this->redirect('finishedPayment', 'PayPal', 'HgonPayment', ['result' => $result->state]);        //===    }    /**     * action finishedPaymentAction     * action for finishing donation money (PayPal)     *     * @param string $result     * @return void     */    public function finishedPaymentAction($result)    {        if ($result  == "approved") {            $this->view->assign('basket', $GLOBALS['TSFE']->fe_user->getKey('ses','hgon_payment_basket'));        }        // remove Session data        $GLOBALS['TSFE']->fe_user->setKey('ses', 'hgon_payment_basket', null);        $GLOBALS['TSFE']->storeSessionData();    }    /**     * action confirmSubscriptionAction     * coming back from paypal with payment authorization     *     * @return void     */    public function confirmSubscriptionAction()    {        // ?subscription_id=I-YXJ3MBF0C4UJ&ba_token=BA-3CT986595A364260E&token=0Y278938SV1387338        // @toDo: Does we need an additional subscription execution, like on one time payments? Obviously not!        // -> We can reactivate, update, cancel subscription etc.        if (            \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('subscription_id')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('token')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('ba_token')            && $GLOBALS['TSFE']->fe_user->getKey('ses', 'hgon_payment_basket')        ) {            /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */            $basket = $GLOBALS['TSFE']->fe_user->getKey('ses','hgon_payment_basket');            $basket->setPaymentData([                'subscriptionId' =>  preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('subscription_id')),                'token' =>  preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('token')),                'baToken' =>  preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('ba_token'))            ]);            /** @var \HGON\HgonPayment\Api\PayPalApi $payPalApi */            $payPalApi = $this->objectManager->get('HGON\\HgonPayment\\Api\\PayPalApi');            $result = $payPalApi->getSubscription(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('subscription_id'));            $GLOBALS['TSFE']->fe_user->setKey('ses', 'hgon_payment_basket', $basket);            $GLOBALS['TSFE']->storeSessionData();            //  obviously not necessary (subscription is already activated)            //  $this->forward('executeSubscription');            $this->sendMails($result, $basket);        //    $this->view->assign('basket', $basket);            $this->forward('finishedSubscription');            //===        }        // if this data set - but we do not have some data in session - show error in view        if (            \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('subscription_id')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('token')            && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('ba_token')        ) {            $this->view->assign('showMessage', true);        }    }    /**     * action executeSubscriptionAction     * action for executing subscriptions (PayPal)     *     * @return void     */    public function executeSubscriptionAction()    {    }    /**     * action finishedSubscriptionAction     * action for finishing donation money (PayPal)     *     * @return void     */    public function finishedSubscriptionAction()    {        $this->view->assign('basket', $GLOBALS['TSFE']->fe_user->getKey('ses','hgon_payment_basket'));        // remove Session data        $GLOBALS['TSFE']->fe_user->setKey('ses', 'hgon_payment_basket', null);        $GLOBALS['TSFE']->storeSessionData();    }    /**     *     * @param mixed $result     * @param mixed $basket     */    private function sendMails($result, $basket) {        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');        /** @var \RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser */        $frontendUser = $objectManager->get('RKW\\RkwRegistration\\Domain\\Model\\FrontendUser');        if ($result->subscriber instanceof \stdClass) {            // subscription            $frontendUser->setEmail($result->subscriber->email_address);            $frontendUser->setFirstName($result->subscriber->name->given_name);            $frontendUser->setLastName($result->subscriber->name->surname);        } elseif ($result->payer instanceof \stdClass) {            // payment            $frontendUser->setEmail($result->payer->payer_info->email);            $frontendUser->setFirstName($result->payer->payer_info->first_name);            $frontendUser->setLastName($result->payer->payer_info->last_name);        }        $frontendUser->setTxRkwregistrationLanguageKey('de');        // @toDo: use just for testing        // $frontendUser->setEmail('maximilian@faesslerweb.de');        /** @var \HGON\HgonPayment\Helper\DataConverter $dataConverter */        $dataConverter = $objectManager->get('HGON\\HgonPayment\\Helper\\DataConverter');        $paymentData = [];        if ($result->subscriber instanceof \stdClass) {            // is subscription            $paymentData = $dataConverter->subscriptionPayPal($result, $basket);        } elseif ($result->payer instanceof \stdClass) {            // is payment            $paymentData = $dataConverter->paymentPayPal($result, $basket);        }        /** @var \HGON\HgonPayment\Service\RkwMailService $rkwMailService */        $rkwMailService = $objectManager->get('HGON\\HgonPayment\\Service\\RkwMailService');        $rkwMailService->confirmPayPalUser($frontendUser, $paymentData);        // workaround to avoid repo is null.        /** @var \RKW\RkwAuthors\Domain\Repository\AuthorsRepository $authorsRepository */        $authorsRepository = $objectManager->get('RKW\\RkwAuthors\\Domain\\Repository\\AuthorsRepository');        if (intval($this->settings['rkwAuthorContactPerson'])) {            $author = $authorsRepository->findByIdentifier(intval($this->settings['rkwAuthorContactPerson']));            if ($author instanceof \RKW\RkwAuthors\Domain\Model\Authors) {                /** @var \RKW\RkwRegistration\Domain\Model\BackendUser $backendUser */                $backendUser = $objectManager->get('RKW\\RkwRegistration\\Domain\\Model\\BackendUser');                $backendUser->setEmail($author->getEmail());                $backendUser->setRealName($author->getFirstName() . ' ' . $author->getLastName());                $backendUser->setLang('de');                $rkwMailService->confirmPayPalAdmin($backendUser, $paymentData, $frontendUser);            }        }        // workaround to avoid repo is null.        /** @var \RKW\RkwRegistration\Domain\Repository\BackendUserRepository $backendUserRepository */        $backendUserRepository = $objectManager->get('RKW\\RkwRegistration\\Domain\\Repository\\BackendUserRepository');        if (intval($this->settings['backendUserContactPerson'])) {            $backendUser = $backendUserRepository->findByIdentifier(intval($this->settings['backendUserContactPerson']));            if ($backendUser instanceof \RKW\RkwRegistration\Domain\Model\BackendUser) {                $rkwMailService->confirmPayPalAdmin($backendUser, $paymentData, $frontendUser);            }        }    }}