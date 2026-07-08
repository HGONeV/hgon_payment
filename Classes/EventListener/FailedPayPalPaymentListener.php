<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Event\ProcessPaymentFailureEvent;
use HGON\HgonPayment\Service\EventPayPalService;

/**
 * Handles sf_event_mgt payment failures for PayPal registrations.
 */
final class FailedPayPalPaymentListener
{
    public function __construct(
        private readonly EventPayPalService $eventPayPalService,
    ) {
    }

    /**
     * Keeps the registration unpaid and displays the failure message.
     */
    public function __invoke(ProcessPaymentFailureEvent $event): void
    {
        if (!$this->eventPayPalService->supports($event->getPaymentMethod())) {
            return;
        }

        $result = $this->eventPayPalService->markPaymentFailed($event->getRegistration());
        $variables = $event->getVariables();
        $variables['html'] = $result['html'];

        $event->setVariables($variables);
        $event->setUpdateRegistration((bool)$result['updateRegistration']);
    }
}
