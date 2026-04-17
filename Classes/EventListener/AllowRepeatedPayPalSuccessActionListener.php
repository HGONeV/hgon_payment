<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Event\ProceedWithPaymentActionEvent;
use HGON\HgonPayment\Service\EventPayPalService;

final class AllowRepeatedPayPalSuccessActionListener
{
    public function __construct(
        private readonly EventPayPalService $eventPayPalService,
    ) {
    }

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
