<?php

declare(strict_types=1);

namespace HGON\HgonPayment\PayPal;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Resolves all PayPal credentials and mode flags from one place.
 *
 * Environment variables intentionally win over TYPO3 extension configuration so
 * stage and live secrets can stay outside the database and outside Git.
 */
final class PayPalConfiguration
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * Returns the merchant name shown in the PayPal checkout UI.
     */
    public function getBrandName(): string
    {
        return trim((string)$this->getConfigValue('brandName', 'HGON')) ?: 'HGON';
    }

    /**
     * Returns the PayPal REST client id for the active environment.
     */
    public function getClientId(): string
    {
        return $this->getRequiredConfigValue('clientId');
    }

    /**
     * Returns the PayPal REST client secret for the active environment.
     */
    public function getClientSecret(): string
    {
        return $this->getRequiredConfigValue('clientSecret');
    }

    /**
     * Tells whether PayPal sandbox endpoints should be used.
     */
    public function isSandboxEnabled(): bool
    {
        return filter_var($this->getConfigValue('sandbox', true), FILTER_VALIDATE_BOOL);
    }

    /**
     * Returns the current PayPal API base URL for v2 checkout requests.
     */
    public function getCheckoutApiBaseUrl(): string
    {
        return $this->isSandboxEnabled()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Returns the current PayPal API base URL for legacy v1 requests.
     */
    public function getLegacyApiBaseUrl(): string
    {
        return $this->isSandboxEnabled()
            ? 'https://api.sandbox.paypal.com'
            : 'https://api.paypal.com';
    }

    /**
     * Reads a required config value and throws a deployment-friendly error.
     */
    private function getRequiredConfigValue(string $key): string
    {
        $value = trim((string)$this->getConfigValue($key, ''));

        if ($value === '') {
            throw new \RuntimeException(sprintf('Die PayPal-Konfiguration "hgon_payment.%s" ist leer.', $key));
        }

        return $value;
    }

    /**
     * Resolves a config key from environment first, then extension configuration.
     */
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

    /**
     * Maps internal config keys to deploy-time environment variables.
     */
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

            if ($GLOBALS['TYPO3_CONF_VARS']['HGON']['hgon_payment']['paypal'][$key] ?? null !== null) {
                return (string)$GLOBALS['TYPO3_CONF_VARS']['HGON']['hgon_payment']['paypal'][$key];
            }


            return null;
        }

        return (string)$value;
    }
}
