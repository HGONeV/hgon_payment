<?php
declare(strict_types=1);

namespace HGON\HgonPayment\Session;

use HGON\HgonPayment\Domain\Model\Basket;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class BasketSessionService
{
    private const KEY = 'hgon_payment_basket';

    /**
     * Basket in der Session speichern
     *
     * @param \HGON\HgonPayment\Domain\Model\Basket $basket
     */
    public function setBasket(Basket $basket): void
    {
        $feUser = $this->getFeUser();
        // TYPO3 serialisiert das Objekt intern, das ist in 9.5 okay
        $feUser->setKey('ses', self::KEY, $basket);
        $feUser->storeSessionData();
    }

    /**
     * Basket aus der Session holen
     *
     * @return \HGON\HgonPayment\Domain\Model\Basket|null
     */
    public function getBasket(): ?Basket
    {
        $feUser = $this->getFeUser();
        $value = $feUser->getKey('ses', self::KEY);

        if ($value instanceof Basket) {
            return $value;
        }

        // Falls noch alte Array-Daten in der Session hängen:
        return null;
    }

    /**
     * Basket aus der Session löschen
     */
    public function clearBasket(): void
    {
        $feUser = $this->getFeUser();
        $feUser->setKey('ses', self::KEY, null);
        $feUser->storeSessionData();
    }

    /**
     * FE-User aus TSFE holen (9.5-kompatibel)
     */
    private function getFeUser(): FrontendUserAuthentication
    {
        /** @var FrontendUserAuthentication $feUser */
        $feUser = $GLOBALS['TSFE']->fe_user;
        return $feUser;
    }
}
