<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Event\ProcessPaymentCancelEvent;
use HGON\HgonPayment\Service\EventPayPalService;

final class CancelledPayPalPaymentListener
{
    public function __construct(
        private readonly EventPayPalService $eventPayPalService,
    ) {
    }

    public function __invoke(ProcessPaymentCancelEvent $event): void
    {
        if (!$this->eventPayPalService->supports($event->getPaymentMethod())) {
            return;
        }

        $result = $this->eventPayPalService->markPaymentCancelled($event->getRegistration());
        $variables = $event->getVariables();
        $variables['html'] = $result['html'];

        $event->setVariables($variables);
        $event->setUpdateRegistration((bool)$result['updateRegistration']);
    }
}
