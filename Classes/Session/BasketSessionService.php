<?php

declare(strict_types=1);

namespace HGON\HgonPayment\Session;

use HGON\HgonPayment\Domain\Model\Basket;
use HGON\HgonTemplate\Utility\FrontendUserUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class BasketSessionService
{
    private const KEY = 'hgon_payment_basket';

    /**
     * Basket in der Session speichern.
     */
    public function setBasket(Basket $basket): void
    {
        $feUser = FrontendUserUtility::getFrontendUserAuthentication();
        if (!$feUser) {
            return;
        }

        $feUser->setKey('ses', self::KEY, $basket);

    }

    /**
     * Basket aus der Session holen.
     */
    public function getBasket(): ?Basket
    {
        $feUser = FrontendUserUtility::getFrontendUserAuthentication();
        if (!$feUser) {
            return null;
        }

        $value = $feUser->getKey('ses', self::KEY);
        return $value instanceof Basket ? $value : null;
    }

    /**
     * Basket aus der Session löschen.
     */
    public function clearBasket(): void
    {
        $feUser = FrontendUserUtility::getFrontendUserAuthentication();
        if (!$feUser) {
            return;
        }

        // setKey erwartet “Daten”; null kann je nach Core-Version/Backend unglücklich sein.
        // Sicherer: Key löschen, falls API vorhanden – sonst auf leeren Wert setzen.
        if (method_exists($feUser, 'setKey')) {
            $feUser->setKey('ses', self::KEY, '');
        }

    }


}
