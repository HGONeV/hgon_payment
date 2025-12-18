<?php

declare(strict_types=1);

namespace HGON\HgonPayment\Session;

use HGON\HgonPayment\Domain\Model\Basket;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class BasketSessionService
{
    private const KEY = 'hgon_payment_basket';

    /**
     * Basket in der Session speichern.
     */
    public function setBasket(Basket $basket): void
    {
        $feUser = $this->getFeUserOrNull();
        if (!$feUser) {
            return;
        }

        $feUser->setKey('ses', self::KEY, $basket);

        // In TYPO3 12 ok (und in Redirect-Szenarien sinnvoll):
        $this->storeSessionDataIfPossible($feUser);
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

        // setKey erwartet “Daten”; null kann je nach Core-Version/Backend unglücklich sein.
        // Sicherer: Key löschen, falls API vorhanden – sonst auf leeren Wert setzen.
        if (method_exists($feUser, 'setKey')) {
            $feUser->setKey('ses', self::KEY, '');
        }

        $this->storeSessionDataIfPossible($feUser);
    }

    /**
     * Modern (TYPO3 11/12/13): FE-User kommt aus dem Request-Attribut "frontend.user".
     * Legacy-Fallback: TSFE (nur falls vorhanden).
     */
    private function getFeUserOrNull(): ?FrontendUserAuthentication
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $feUser = null;

        if ($request && method_exists($request, 'getAttribute')) {
            $feUser = $request->getAttribute('frontend.user');
        }

        if (!$feUser) {
            $tsfe = $GLOBALS['TSFE'] ?? null;
            $feUser = $tsfe->fe_user ?? null;
        }

        return $feUser instanceof FrontendUserAuthentication ? $feUser : null;
    }

    /**
     * TYPO3 12: storeSessionData() existiert und ist der richtige Weg für FE-User-Sessions,
     * wenn du sofort persistieren willst (z.B. vor Redirect).
     */
    private function storeSessionDataIfPossible(FrontendUserAuthentication $feUser): void
    {
        if (method_exists($feUser, 'storeSessionData')) {
            $feUser->storeSessionData();
        }
    }
}
