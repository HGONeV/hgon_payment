<?php

declare(strict_types=1);

namespace HGON\HgonPayment\Service;

use DERHANSEN\SfEventMgt\Domain\Model\Registration;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class EventPayPalService
{
    private const METHOD = 'paypal';

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function supports(string $paymentMethod): bool
    {
        return $paymentMethod === self::METHOD;
    }

    public function initializePayment(
        Registration $registration,
        string $successUrl,
        string $cancelUrl,
        string $failureUrl
    ): array {
        $registration->setPaid(false);

        try {
            $order = $this->createOrder($registration, $successUrl, $cancelUrl, $failureUrl);
            $approveUrl = $this->extractApproveUrl($order);
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
            $capture = $this->captureOrder($orderId);
            $captureId = $this->extractCaptureId($capture);
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
                'brand_name' => $this->getConfigValue('brandName', 'HGON'),
                'user_action' => 'PAY_NOW',
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $response = $this->request(
            'POST',
            $this->getApiBaseUrl() . '/v2/checkout/orders',
            $payload
        );

        $statusCode = $response->getStatusCode();
        $body = $this->decodeResponse($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException($this->buildApiErrorMessage($body, $statusCode));
        }

        return $body;
    }

    private function captureOrder(string $orderId): array
    {
        $response = $this->request(
            'POST',
            $this->getApiBaseUrl() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
            new \stdClass()
        );

        $statusCode = $response->getStatusCode();
        $body = $this->decodeResponse($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException($this->buildApiErrorMessage($body, $statusCode));
        }

        return $body;
    }

    private function request(string $method, string $url, array|\stdClass|null $payload = null): ResponseInterface
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
        ];

        $options = [
            'headers' => $headers,
            'allow_redirects' => false,
        ];

        if ($payload !== null) {
            $options['body'] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        return $this->requestFactory->request($url, $method, $options);
    }

    private function getAccessToken(): string
    {
        $clientId = $this->getRequiredConfigValue('clientId');
        $clientSecret = $this->getRequiredConfigValue('clientSecret');

        $response = $this->requestFactory->request(
            $this->getApiBaseUrl() . '/v1/oauth2/token',
            'POST',
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'de_DE',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                ],
                'body' => 'grant_type=client_credentials',
                'allow_redirects' => false,
            ]
        );

        $statusCode = $response->getStatusCode();
        $body = $this->decodeResponse($response);
        $accessToken = (string)($body['access_token'] ?? '');

        if ($statusCode < 200 || $statusCode >= 300 || $accessToken === '') {
            throw new \RuntimeException($this->buildApiErrorMessage($body, $statusCode));
        }

        return $accessToken;
    }

    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string)$response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Die Antwort von PayPal konnte nicht gelesen werden.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function extractApproveUrl(array $order): string
    {
        foreach (($order['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve' && is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        return '';
    }

    private function extractCaptureId(array $capture): string
    {
        $purchaseUnits = $capture['purchase_units'] ?? [];
        $firstPurchaseUnit = is_array($purchaseUnits) ? ($purchaseUnits[0] ?? []) : [];
        $payments = is_array($firstPurchaseUnit) ? ($firstPurchaseUnit['payments'] ?? []) : [];
        $captures = is_array($payments) ? ($payments['captures'] ?? []) : [];
        $firstCapture = is_array($captures) ? ($captures[0] ?? []) : [];

        return is_array($firstCapture) ? (string)($firstCapture['id'] ?? '') : '';
    }

    private function buildApiErrorMessage(array $body, int $statusCode): string
    {
        $parts = [];

        if (($body['message'] ?? '') !== '') {
            $parts[] = (string)$body['message'];
        }

        if (($body['error_description'] ?? '') !== '') {
            $parts[] = (string)$body['error_description'];
        }

        if (($body['details'][0]['description'] ?? '') !== '') {
            $parts[] = (string)$body['details'][0]['description'];
        }

        $message = trim(implode(' ', $parts));

        if ($message === '') {
            $message = 'PayPal lieferte keinen verwertbaren Fehlertext.';
        }

        return sprintf('%s (HTTP %d)', $message, $statusCode);
    }

    private function getApiBaseUrl(): string
    {
        if ($this->isSandboxEnabled()) {
            return 'https://api-m.sandbox.paypal.com';
        }

        return 'https://api-m.paypal.com';
    }

    private function isSandboxEnabled(): bool
    {
        return filter_var($this->getConfigValue('sandbox', true), FILTER_VALIDATE_BOOL);
    }

    private function getRequiredConfigValue(string $key): string
    {
        $value = trim((string)$this->getConfigValue($key, ''));

        if ($value === '') {
            throw new \RuntimeException(sprintf('Die Extension-Konfiguration "hgon_payment.%s" ist leer.', $key));
        }

        return $value;
    }

    private function getConfigValue(string $key, mixed $default = null): mixed
    {
        $environmentValue = $this->getEnvironmentConfigValue($key);
        if ($environmentValue !== null) {
            return $environmentValue;
        }

        try {
            $configuration = $this->extensionConfiguration->get('hgon_payment');
        } catch (\Throwable) {
            $configuration = [];
        }

        return $configuration[$key] ?? $default;
    }

    private function getEnvironmentConfigValue(string $key): ?string
    {
        $environmentKey = match ($key) {
            'brandName' => 'HGON_PAYMENT_PAYPAL_BRAND_NAME',
            'clientId' => 'HGON_PAYMENT_PAYPAL_CLIENT_ID',
            'clientSecret' => 'HGON_PAYMENT_PAYPAL_CLIENT_SECRET',
            'sandbox' => 'HGON_PAYMENT_PAYPAL_SANDBOX',
            default => '',
        };

        if ($environmentKey === '') {
            return null;
        }

        $value = getenv($environmentKey);
        if ($value === false || trim((string)$value) === '') {
            return null;
        }

        return (string)$value;
    }

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

    private function getSuccessTitle(): string
    {
        return $this->translate('payment.event.paypal.success.title', 'Payment successful');
    }

    private function getRedirectTitle(): string
    {
        return $this->translate('payment.event.paypal.redirect.title', 'Redirecting to PayPal');
    }

    private function getRedirectMessage(): string
    {
        return $this->translate(
            'payment.event.paypal.redirect.message',
            'You are now being redirected to PayPal to complete your registration.'
        );
    }

    private function getRedirectLinkText(): string
    {
        return $this->translate(
            'payment.event.paypal.redirect.linkText',
            'If the redirect does not start automatically, please click here.'
        );
    }

    private function getSuccessMessage(): string
    {
        return $this->translate(
            'payment.event.paypal.success.message',
            'The PayPal payment was recorded successfully. Your registration is now confirmed.'
        );
    }

    private function translate(string $key, string $fallback): string
    {
        $translation = LocalizationUtility::translate($key, 'hgon_payment');

        return is_string($translation) && $translation !== '' ? $translation : $fallback;
    }

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

    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 1) . '.';
    }
}
