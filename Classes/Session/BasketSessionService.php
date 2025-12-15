<?php

declare(strict_types=1);

namespace HGON\HgonPayment\Session;

use HGON\HgonPayment\Domain\Model\Basket;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class BasketSessionService
{
    private const KEY = 'hgon_payment_basket';

    /**
     * Basket in der Session speichern.
     * TYPO3 10: storeSessionData() ist entfernt – setKey() reicht i.d.R., Persistenz passiert am Request-Ende.
     */
    public function setBasket(Basket $basket): void
    {
        $feUser = $this->getFeUserOrNull();
        if (!$feUser) {
            return;
        }

        $feUser->setKey('ses', self::KEY, $basket);

        // Optional: nur nötig, wenn du danach sofort einen Redirect machst oder
        // im gleichen Request garantiert wieder lesen musst.
        $this->persistFrontendUserSessionIfPossible($feUser);
    }

    /**
     * Basket aus der Session holen.
     */
    public function getBasket(): ?Basket
    {
        $feUser = $this->getFeUserOrNull();
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
        $feUser = $this->getFeUserOrNull();
        if (!$feUser) {
            return;
        }

        $feUser->setKey('ses', self::KEY, null);

        // Optional wie oben
        $this->persistFrontendUserSessionIfPossible($feUser);
    }

    /**
     * FE-User holen (TYPO3 10.4: TSFE ist legacy, aber noch verfügbar im FE).
     * Rückgabe null, wenn kein FE-Kontext vorhanden ist.
     */
    private function getFeUserOrNull(): ?FrontendUserAuthentication
    {
        $tsfe = $GLOBALS['TSFE'] ?? null;
        $feUser = $tsfe->fe_user ?? null;

        return $feUser instanceof FrontendUserAuthentication ? $feUser : null;
    }

    /**
     * Ersatz für storeSessionData(): Persistierung über SessionManager.
     * Robust: nur wenn Session-Objekt vorhanden ist.
     */
    private function persistFrontendUserSessionIfPossible(FrontendUserAuthentication $feUser): void
    {
        // TYPO3 10.4 hat i.d.R. getSession(); defensiv bleiben, falls nicht.
        if (!method_exists($feUser, 'getSession')) {
            return;
        }

        $session = $feUser->getSession();
        if ($session === null) {
            return;
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = GeneralUtility::makeInstance(SessionManager::class);
        $sessionManager->updateSession($session);
    }
}
