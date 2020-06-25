<?php

namespace HGON\HgonPayment\Helper;
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * DataConverter
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright HGON
 * @package Hgon_payment
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class DataConverter implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * convert mollie subscription data to a small array
     *
     * @param \Mollie\Api\Resources\Subscription $mollieSubscription
     * @param \Mollie\Api\Resources\Customer $mollieCustomer
     * @return array
     */
    public function subscriptionMollie(\Mollie\Api\Resources\Subscription $mollieSubscription, \Mollie\Api\Resources\Customer $mollieCustomer)
    {
        $mollieDataArray = [];
        $mollieDataArray['subscription']['type'] = 'Mollie';
        $mollieDataArray['subscription']['amount']['value'] = $mollieSubscription->amount->value;
        $mollieDataArray['subscription']['startDate'] = $mollieSubscription->startDate;

        $mollieDataArray['customer']['id'] = $mollieCustomer->id;
        $mollieDataArray['customer']['name'] = $mollieCustomer->name;
        $mollieDataArray['customer']['email'] = $mollieCustomer->email;

        return $mollieDataArray;
        //===
    }


    /**
     * convert paypal payment data to a small array
     *
     * @param \stdClass $payPalPayment
     * @param \HGON\HgonPayment\Domain\Model\Basket $basket
     * @return array
     */
    public function paymentPayPal(\stdClass $payPalPayment, \HGON\HgonPayment\Domain\Model\Basket $basket)
    {
        $payPalDataArray = [];
        $payPalDataArray['payment']['type'] = 'PayPal Payment';
        $payPalDataArray['payment']['amount']['value'] = $basket->getTotal();

        $payPalDataArray['customer']['id'] = $payPalPayment->id;
        $payPalDataArray['customer']['name'] = $payPalPayment->payer->payer_info->first_name . ' ' . $payPalPayment->payer->payer_info->last_name;
        $payPalDataArray['customer']['email'] = $payPalPayment->payer->payer_info->email;
        $payPalDataArray['customer']['address']['recipientName'] = $payPalPayment->payer->payer_info->shipping_address->recipient_name;
        $payPalDataArray['customer']['address']['line1'] = $payPalPayment->payer->payer_info->shipping_address->line1;
        $payPalDataArray['customer']['address']['city'] = $payPalPayment->payer->payer_info->shipping_address->city;
        $payPalDataArray['customer']['address']['state'] = $payPalPayment->payer->payer_info->shipping_address->state;
        $payPalDataArray['customer']['address']['postalCode'] = $payPalPayment->payer->payer_info->shipping_address->postal_code;
        $payPalDataArray['customer']['address']['countryCode'] = $payPalPayment->payer->payer_info->shipping_address->country_code;

        $i = 0;
        /** @var \HGON\HgonPayment\Domain\Model\Article $article */
        foreach ($basket->getArticle() as $article) {
            $payPalDataArray['articleList'][$i]['name'] = $article->getName();
            $payPalDataArray['articleList'][$i]['price'] = $article->getPrice();
            $payPalDataArray['articleList'][$i]['isDonation'] = $article->getIsDonation();
            $i++;

            // Currently we have always only one article at once. So mark whole payment as donation or not (article sale)
            $payPalDataArray['payment']['isDonation'] = $article->getIsDonation() ? 1 : 2;
        }

        return $payPalDataArray;
        //===
    }
}
