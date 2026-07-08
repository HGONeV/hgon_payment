<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Event\ProcessPaymentInitializeEvent;
use HGON\HgonPayment\Service\EventPayPalService;

/**
 * Starts the PayPal checkout when sf_event_mgt initializes payment.
 */
final class InitializePayPalPaymentListener
{
    public function __construct(
        private readonly EventPayPalService $eventPayPalService,
    ) {
    }

    /**
     * Replaces the default payment output with PayPal redirect markup.
     */
    public function __invoke(ProcessPaymentInitializeEvent $event): void
    {
        if (!$this->eventPayPalService->supports($event->getPaymentMethod())) {
            return;
        }

        $variables = $event->getVariables();
        $result = $this->eventPayPalService->initializePayment(
            $event->getRegistration(),
            (string)($variables['successUrl'] ?? ''),
            (string)($variables['cancelUrl'] ?? ''),
            (string)($variables['failureUrl'] ?? '')
        );

        $variables['html'] = $result['html'];
        $event->setVariables($variables);
        $event->setUpdateRegistration((bool)$result['updateRegistration']);
    }
}
