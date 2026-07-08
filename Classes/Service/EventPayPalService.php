<?php

declare(strict_types=1);

namespace HGON\HgonPayment\Service;

use DERHANSEN\SfEventMgt\Domain\Model\Registration;
use HGON\HgonPayment\PayPal\PayPalCheckoutClient;
use HGON\HgonPayment\PayPal\PayPalConfiguration;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Coordinates PayPal payments for sf_event_mgt registrations.
 *
 * The service intentionally contains event-domain decisions only. PayPal HTTP
 * communication and credentials live in the PayPal namespace.
 */
final class EventPayPalService
{
    private const METHOD = 'paypal';

    public function __construct(
        private readonly PayPalCheckoutClient $payPalClient,
        private readonly PayPalConfiguration $payPalConfiguration,
    ) {
    }

    /**
     * Checks whether this service should handle the selected payment method.
     */
    public function supports(string $paymentMethod): bool
    {
        return $paymentMethod === self::METHOD;
    }

    /**
     * Creates the PayPal order and returns redirect HTML for sf_event_mgt.
     */
    public function initializePayment(
        Registration $registration,
        string $successUrl,
        string $cancelUrl,
        string $failureUrl
    ): array {
        $registration->setPaid(false);

        try {
            $order = $this->createOrder($registration, $successUrl, $cancelUrl, $failureUrl);
            $approveUrl = $this->payPalClient->extractApproveUrl($order);
            $orderId = (string)($order['id'] ?? '');

            if ($approveUrl === '' || $orderId === '') {
                throw new \RuntimeException('PayPal-Antwort enthaelt keine Freigabe-URL.');
            }

            $registration->setPaymentReference($orderId);

            return [
                'updateRegistration' => true,
                'html' => $this->renderRedirectHtml($approveUrl),
            ];
        } catch (\Throwable $exception) {
            return [
                'updateRegistration' => true,
                'html' => $this->renderErrorHtml(
                    'Die PayPal-Zahlung konnte gerade nicht initialisiert werden.',
                    $exception->getMessage()
                ),
            ];
        }
    }

    /**
     * Captures a successful PayPal order and updates the registration state.
     */
    public function confirmSuccessfulPayment(Registration $registration, array $queryParameters): array
    {
        if ($registration->getPaid()) {
            return [
                'updateRegistration' => false,
                'html' => $this->renderSuccessHtml(),
                'title' => $this->getSuccessTitle(),
                'message' => $this->getSuccessMessage(),
                'renderSuccessCard' => true,
            ];
        }

        $orderId = (string)($queryParameters['token'] ?? $registration->getPaymentReference() ?? '');

        if ($orderId === '') {
            $registration->setPaid(false);

            return [
                'updateRegistration' => true,
                'html' => $this->renderErrorHtml(
                    'Die PayPal-Rueckmeldung ist unvollstaendig.',
                    'Es wurde keine Order-ID uebermittelt.'
                ),
            ];
        }

        try {
            $capture = $this->payPalClient->captureOrder($orderId);
            $captureId = $this->payPalClient->extractCaptureId($capture);
            $status = strtoupper((string)($capture['status'] ?? ''));

            if (!in_array($status, ['COMPLETED', 'APPROVED'], true)) {
                throw new \RuntimeException(sprintf('Unerwarteter PayPal-Status: %s', $status ?: 'leer'));
            }

            $registration->setConfirmed(true);
            $registration->setPaid(true);
            $registration->setPaymentReference($captureId !== '' ? $captureId : $orderId);

            return [
                'updateRegistration' => true,
                'sendNotifications' => true,
                'html' => $this->renderSuccessHtml(),
                'title' => $this->getSuccessTitle(),
                'message' => $this->getSuccessMessage(),
                'renderSuccessCard' => true,
            ];
        } catch (\Throwable $exception) {
            $registration->setPaid(false);

            return [
                'updateRegistration' => true,
                'html' => $this->renderErrorHtml(
                    'Die PayPal-Zahlung konnte nicht bestätigt werden.',
                    $exception->getMessage()
                ),
            ];
        }
    }

    /**
     * Marks a registration as unpaid after a PayPal failure callback.
     */
    public function markPaymentFailed(Registration $registration): array
    {
        $registration->setPaid(false);

        return [
            'updateRegistration' => true,
            'html' => $this->renderErrorHtml(
                'Die PayPal-Zahlung ist fehlgeschlagen.',
                'Die Anmeldung bleibt gespeichert. Die Zahlung ist noch offen.'
            ),
        ];
    }

    /**
     * Marks a registration as unpaid after the user cancelled at PayPal.
     */
    public function markPaymentCancelled(Registration $registration): array
    {
        $registration->setPaid(false);

        return [
            'updateRegistration' => true,
            'html' => $this->renderErrorHtml(
                'Die PayPal-Zahlung wurde abgebrochen.',
                'Die Anmeldung bleibt gespeichert. Die Zahlung ist noch offen.'
            ),
        ];
    }

    /**
     * Builds the PayPal order payload for an event registration.
     */
    private function createOrder(
        Registration $registration,
        string $successUrl,
        string $cancelUrl,
        string $failureUrl
    ): array {
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'registration-' . $registration->getUid(),
                'description' => $this->truncate(
                    sprintf(
                        'Anmeldung %d fuer %s',
                        $registration->getUid(),
                        (string)$registration->getEvent()?->getTitle()
                    ),
                    127
                ),
                'custom_id' => (string)$registration->getUid(),
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => number_format((float)$registration->getPrice(), 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => $this->payPalConfiguration->getBrandName(),
                'user_action' => 'PAY_NOW',
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        return $this->payPalClient->createOrder($payload);
    }

    /**
     * Renders the immediate redirect page returned to sf_event_mgt.
     */
    private function renderRedirectHtml(string $approveUrl): string
    {
        $escapedUrl = htmlspecialchars($approveUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $headline = htmlspecialchars($this->getRedirectTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($this->getRedirectMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $linkText = htmlspecialchars($this->getRedirectLinkText(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<section class="section">
    <div class="section__content">
        <div class="flex-container space-between gutter valign-stretch">
            <div class="flex-item c-12">
                <article class="card card-plainfull">
                    <div class="card__inner">
                        <div class="flex-container">
                            <div class="flex-item c-4">
                                <header class="card__header">
                                    <h1 class="headline">{$headline}</h1>
                                </header>
                            </div>
                            <div class="flex-item c-7">
                                <div class="card__content">
                                    <p>{$message}</p>
                                    <p><a class="btn btn--rounded btn--tertiary" href="{$escapedUrl}">{$linkText}</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>
<script>
window.setTimeout(function () {
    window.location.href = '{$escapedUrl}';
}, 300);
</script>
HTML;
    }

    /**
     * Renders the final success message after PayPal capture.
     */
    private function renderSuccessHtml(): string
    {
        $headline = htmlspecialchars($this->getSuccessTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($this->getSuccessMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<section class="section">
    <div class="section__content">
        <div class="flex-container space-between gutter valign-stretch">
            <div class="flex-item c-12">
                <article class="card card-plainfull">
                    <div class="card__inner">
                        <div class="flex-container">
                            <div class="flex-item c-4">
                                <header class="card__header">
                                    <h1 class="headline">{$headline}</h1>
                                </header>
                            </div>
                            <div class="flex-item c-7">
                                <div class="card__content">
                                    <p>{$message}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>
HTML;
    }

    /**
     * Returns the translated success headline.
     */
    private function getSuccessTitle(): string
    {
        return $this->translate('payment.event.paypal.success.title', 'Payment successful');
    }

    /**
     * Returns the translated redirect headline.
     */
    private function getRedirectTitle(): string
    {
        return $this->translate('payment.event.paypal.redirect.title', 'Redirecting to PayPal');
    }

    /**
     * Returns the translated redirect body text.
     */
    private function getRedirectMessage(): string
    {
        return $this->translate(
            'payment.event.paypal.redirect.message',
            'You are now being redirected to PayPal to complete your registration.'
        );
    }

    /**
     * Returns the translated manual redirect link text.
     */
    private function getRedirectLinkText(): string
    {
        return $this->translate(
            'payment.event.paypal.redirect.linkText',
            'If the redirect does not start automatically, please click here.'
        );
    }

    /**
     * Returns the translated success body text.
     */
    private function getSuccessMessage(): string
    {
        return $this->translate(
            'payment.event.paypal.success.message',
            'The PayPal payment was recorded successfully. Your registration is now confirmed.'
        );
    }

    /**
     * Translates a label and falls back if no localization is available.
     */
    private function translate(string $key, string $fallback): string
    {
        $translation = LocalizationUtility::translate($key, 'hgon_payment');

        return is_string($translation) && $translation !== '' ? $translation : $fallback;
    }

    /**
     * Renders an error block for payment failures and API errors.
     */
    private function renderErrorHtml(string $headline, string $detail): string
    {
        $escapedHeadline = htmlspecialchars($headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedDetail = htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<div class="payment payment--error">
    <h1>{$escapedHeadline}</h1>
    <p>{$escapedDetail}</p>
</div>
HTML;
    }

    /**
     * Shortens PayPal descriptions to API limits without breaking UTF-8.
     */
    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 1) . '.';
    }
}
