<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Event\ProceedWithPaymentActionEvent;
use HGON\HgonPayment\Service\EventPayPalService;

/**
 * Allows users to revisit the success action after PayPal callbacks.
 */
final class AllowRepeatedPayPalSuccessActionListener
{
    public function __construct(
        private readonly EventPayPalService $eventPayPalService,
    ) {
    }

    /**
     * Disables the default paid-check only for PayPal success handling.
     */
    public function __invoke(ProceedWithPaymentActionEvent $event): void
    {
        if ($event->getActionName() !== 'successAction') {
            return;
        }

        if (!$this->eventPayPalService->supports($event->getRegistration()->getPaymentmethod())) {
            return;
        }

        $event->setPerformPaidCheck(false);
    }
}
