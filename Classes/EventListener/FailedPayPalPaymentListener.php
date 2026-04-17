<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Event\ProcessPaymentFailureEvent;
use HGON\HgonPayment\Service\EventPayPalService;

final class FailedPayPalPaymentListener
{
    public function __construct(
        private readonly EventPayPalService $eventPayPalService,
    ) {
    }

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
