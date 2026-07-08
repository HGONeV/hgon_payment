<?php

declare(strict_types=1);

namespace HGON\HgonPayment\PayPal;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Small client for the current PayPal Checkout Orders API.
 *
 * This class owns HTTP details, authentication and PayPal response parsing. The
 * calling services keep their domain-specific payment flow decisions.
 */
final class PayPalCheckoutClient
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly PayPalConfiguration $configuration,
    ) {
    }

    /**
     * Creates a PayPal order and returns the decoded API response.
     */
    public function createOrder(array $payload): array
    {
        return $this->request(
            'POST',
            $this->configuration->getCheckoutApiBaseUrl() . '/v2/checkout/orders',
            $payload
        );
    }

    /**
     * Captures an approved PayPal order and returns the decoded API response.
     */
    public function captureOrder(string $orderId): array
    {
        return $this->request(
            'POST',
            $this->configuration->getCheckoutApiBaseUrl() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
            new \stdClass()
        );
    }

    /**
     * Extracts the approval URL users need to visit at PayPal.
     */
    public function extractApproveUrl(array $order): string
    {
        foreach (($order['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve' && is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        return '';
    }

    /**
     * Extracts the capture id from a PayPal capture response.
     */
    public function extractCaptureId(array $capture): string
    {
        $purchaseUnits = $capture['purchase_units'] ?? [];
        $firstPurchaseUnit = is_array($purchaseUnits) ? ($purchaseUnits[0] ?? []) : [];
        $payments = is_array($firstPurchaseUnit) ? ($firstPurchaseUnit['payments'] ?? []) : [];
        $captures = is_array($payments) ? ($payments['captures'] ?? []) : [];
        $firstCapture = is_array($captures) ? ($captures[0] ?? []) : [];

        return is_array($firstCapture) ? (string)($firstCapture['id'] ?? '') : '';
    }

    /**
     * Sends an authenticated JSON request and validates the response status.
     */
    private function request(string $method, string $url, array|\stdClass|null $payload = null): array
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

        $response = $this->requestFactory->request($url, $method, $options);
        $body = $this->decodeResponse($response);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException($this->buildApiErrorMessage($body, $statusCode));
        }

        return $body;
    }

    /**
     * Requests a short-lived PayPal access token for subsequent API calls.
     */
    private function getAccessToken(): string
    {
        $response = $this->requestFactory->request(
            $this->configuration->getCheckoutApiBaseUrl() . '/v1/oauth2/token',
            'POST',
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'de_DE',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        $this->configuration->getClientId() . ':' . $this->configuration->getClientSecret()
                    ),
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

    /**
     * Decodes PayPal JSON responses into arrays.
     */
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

    /**
     * Builds a readable error message from common PayPal error formats.
     */
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
}
