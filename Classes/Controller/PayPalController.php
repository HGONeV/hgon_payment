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

use HGON\HgonPayment\Session\BasketSessionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Beuser\Domain\Repository\BackendUserRepository;
use TYPO3\CMS\Beuser\Domain\Model\BackendUser;
use HGON\HgonPayment\Helper\DataConverter;
use HGON\HgonPayment\Service\PaymentMailService;

/**
 * PayPalController
 */
class PayPalController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected BasketSessionService $basketSessionService;

    /**
     * @var \TYPO3\CMS\Beuser\Domain\Repository\BackendUserRepository
     */
    protected BackendUserRepository $backendUserRepository;

    public function __construct(
        ?BasketSessionService $basketSessionService = null,
        ?BackendUserRepository $backendUserRepository = null
    ) {
        $this->basketSessionService = $basketSessionService ?? GeneralUtility::makeInstance(BasketSessionService::class);
        $this->backendUserRepository = $backendUserRepository ?? GeneralUtility::makeInstance(BackendUserRepository::class);
    }

    public function injectBackendUserRepository(BackendUserRepository $backendUserRepository): void
    {
        $this->backendUserRepository = $backendUserRepository;
    }

    public function addToBasketAction(): \Psr\Http\Message\ResponseInterface
    {
        $basket = $this->basketSessionService->getBasket();
        // ... basket anpassen ...
        // $this->basketSessionService->setBasket($basket);
        return $this->htmlResponse();

    }

    public function clearBasketAction(): \Psr\Http\Message\ResponseInterface
    {
        $this->basketSessionService->clearBasket();
        return $this->htmlResponse();

    }


    /**
     * action confirmPaymentAction
     * coming back from paypal with payment authorization
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function confirmPaymentAction(): \Psr\Http\Message\ResponseInterface
    {
        $queryParams = $this->request->getQueryParams();
        $bodyParams = $this->request->getParsedBody();
        $bodyParams = is_array($bodyParams) ? $bodyParams : [];

        // Paypal-Callback kommt i.d.R. per GET, aber wir lesen sicherheitshalber beides
        $paymentId = (string)($queryParams['paymentId'] ?? $bodyParams['paymentId'] ?? '');
        $token     = (string)($queryParams['token']     ?? $bodyParams['token']     ?? '');
        $payerId   = (string)($queryParams['PayerID']   ?? $bodyParams['PayerID']   ?? '');

        $hasParams = ($paymentId !== '' && $token !== '' && $payerId !== '');
        $hasBasket = $this->basketSessionService->getBasket() !== null;

        if ($hasParams && $hasBasket) {
            /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */
            $basket = $this->basketSessionService->getBasket();

            // gleiche "Whitelist"-Sanitization wie vorher
            $basket->setPaymentData([
                'paymentId' => preg_replace('/[^A-Z0-9-]/', '', $paymentId),
                'token'     => preg_replace('/[^A-Z0-9-]/', '', $token),
                'payerId'   => preg_replace('/[^A-Z0-9-]/', '', $payerId),
            ]);

            $this->basketSessionService->setBasket($basket);

            return new \TYPO3\CMS\Extbase\Http\ForwardResponse('executePayment');
        }

        // Parameter da, aber Session/Basket fehlt => Fehlhinweis anzeigen
        if ($hasParams) {
            $this->view->assign('showMessage', true);
        }

        return $this->htmlResponse();
    }



    /**
     * action executePaymentAction
     * action for executing donation money (PayPal)
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function executePaymentAction()
    {
        /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */
        //$basket = $this->session->get('hgon_payment_basket');
        $basket = $this->basketSessionService->getBasket();
        $paymentDataArray = $basket->getPaymentData();

        /** @var \HGON\HgonPayment\Api\PayPalApi $payPalApi */
        $payPalApi = GeneralUtility::makeInstance(\HGON\HgonPayment\Api\PayPalApi::class);
        $result = $payPalApi->executePayment($paymentDataArray['paymentId'], $paymentDataArray['token'], $paymentDataArray['payerId']);

        // $this->view->assign('basket', $basket);

        //    DebuggerUtility::var_dump($basket);
        //    DebuggerUtility::var_dump($result); exit;

        // @toDo: Save Order

        // Mailing
        if ($result->state == "approved") {
            $this->sendMails($result, $basket);
        }

        // the redirect removes the payment data from url
        return $this->redirect('finishedPayment', 'PayPal', 'HgonPayment', ['result' => $result->state]);
    }



    /**
     * action finishedPaymentAction
     * action for finishing donation money (PayPal)
     *
     * @param string $result
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function finishedPaymentAction($result)
    {

        if ($result  == "approved") {
            $this->view->assign('basket', $this->basketSessionService->getBasket());
        }

        // remove Session data
        $this->basketSessionService->clearBasket();

        return $this->htmlResponse();
    }



    /**
     * action confirmSubscriptionAction
     * coming back from paypal with payment authorization
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function confirmSubscriptionAction(): \Psr\Http\Message\ResponseInterface
    {
        $qp = $this->request->getQueryParams();

        $subscriptionId = (string)($qp['subscription_id'] ?? '');
        $token          = (string)($qp['token'] ?? '');
        $baToken        = (string)($qp['ba_token'] ?? '');

        $hasParams = ($subscriptionId !== '' && $token !== '' && $baToken !== '');
        $hasBasket = $this->basketSessionService->getBasket() !== null;

        if ($hasParams && $hasBasket) {
            /** @var \HGON\HgonPayment\Domain\Model\Basket $basket */
            $basket = $this->basketSessionService->getBasket();

            $basket->setPaymentData([
                'subscriptionId' => preg_replace('/[^A-Z0-9-]/', '', $subscriptionId),
                'token'          => preg_replace('/[^A-Z0-9-]/', '', $token),
                'baToken'        => preg_replace('/[^A-Z0-9-]/', '', $baToken),
            ]);

            /** @var \HGON\HgonPayment\Api\PayPalApi $payPalApi */
            $payPalApi = GeneralUtility::makeInstance(\HGON\HgonPayment\Api\PayPalApi::class);
            $result = $payPalApi->getSubscription($subscriptionId);

            $this->basketSessionService->setBasket($basket);

            $this->sendMails($result, $basket);

            // Redirect removes payment data from URL
            return $this->redirect('finishedSubscription');
        }

        // Params da, aber kein Basket (oder Session leer) -> Fehlhinweis
        if ($hasParams) {
            $this->view->assign('showMessage', true);
        }

        return $this->htmlResponse();
    }



    /**
     * action executeSubscriptionAction
     * action for executing subscriptions (PayPal)
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function executeSubscriptionAction()
    {
        return $this->htmlResponse();
    }



    /**
     * action finishedSubscriptionAction
     * action for finishing donation money (PayPal)
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function finishedSubscriptionAction()
    {
        $this->view->assign('basket', $this->basketSessionService->getBasket());

        // remove Session data
        $this->basketSessionService->clearBasket();
        return $this->htmlResponse();
    }


    /**
     *
     * @param mixed $result
     * @param mixed $basket
     */
    private function sendMails($result, $basket): void
    {
        /** @var DataConverter $dataConverter */
        $dataConverter = GeneralUtility::makeInstance(DataConverter::class);

        $paymentData = [];
        if ($result->subscriber instanceof \stdClass) {
            $paymentData = $dataConverter->subscriptionPayPal($result, $basket);
        } elseif ($result->payer instanceof \stdClass) {
            $paymentData = $dataConverter->paymentPayPal($result, $basket);
        }

        /** @var PaymentMailService $paymentMailService */
        $paymentMailService = GeneralUtility::makeInstance(PaymentMailService::class);
        $paymentMailService->confirmPayPalUser($paymentData);

        /** @var BackendUserRepository $backendUserRepository */
        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);

        $backendUserId = (int)($this->settings['backendUserContactPerson'] ?? 0);
        if ($backendUserId > 0) {
            $backendUser = $backendUserRepository->findByIdentifier($backendUserId);
            if ($backendUser instanceof BackendUser) {
                $paymentMailService->confirmPayPalAdmin($backendUser, $paymentData);
            }
        }
    }



}
