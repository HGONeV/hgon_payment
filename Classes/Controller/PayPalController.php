<?php
namespace HGON\HgonPayment\Controller;

/***
 *
 * This file is part of the "HGON Donation" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2018 Maximilian Fäßler <maximilian@faesslerweb.de>, Fäßler Web UG
 *
 ***/
use HGON\HgonDonation\Helper\Donation as DonationHelper;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PayPalController
 */
class PayPalController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * action confirmPaymentAction
     * coming back from paypal with payment authorization
     *
     * @return void
     */
    public function confirmPaymentAction()
    {
        /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */
        $basket = $GLOBALS['TSFE']->fe_user->getKey('ses','hgon_payment_basket');
        $basket->setPaymentData([
            'paymentId' =>  preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('paymentId')),
            'token' =>  preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('token')),
            'payerId' =>  preg_replace('/[^A-Z0-9-]/', '', \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PayerID'))
        ]);

        $GLOBALS['TSFE']->fe_user->setKey('ses', 'hgon_payment_basket', $basket);
        $GLOBALS['TSFE']->storeSessionData();

        $this->view->assign('basket', $basket);
        $this->view->assign('total', $basket->getTotal());
    }



    /**
     * action executePaymentAction
     * action for executing donation money (PayPal)
     *
     * @return void
     */
    public function executePaymentAction()
    {
        /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */
        $basket = $GLOBALS['TSFE']->fe_user->getKey('ses','hgon_payment_basket');
        $paymentDataArray = $basket->getPaymentData();

        /** @var \HGON\HgonPayment\Api\PayPalApi $payPalApi */
        $payPalApi = $this->objectManager->get('HGON\\HgonPayment\\Api\\PayPalApi');
        $result = $payPalApi->executePayment($paymentDataArray['paymentId'], $paymentDataArray['token'], $paymentDataArray['payerId']);

        // @toDo: Save Order

        // @toDo: Send Mail - Oder erledigt das PayPal?? :)
    }



}
