<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository;
use DERHANSEN\SfEventMgt\Event\AfterRegistrationConfirmedEvent;

final class MarkPendingPaidRegistrationsListener
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository,
    ) {
    }

    public function __invoke(AfterRegistrationConfirmedEvent $event): void
    {
        $registration = $event->getRegistration();
        $paymentMethod = $registration->getPaymentmethod();

        if (!$registration->getEvent()?->getEnablePayment()) {
            return;
        }

        if (!in_array($paymentMethod, ['paypal', 'transfer'], true)) {
            return;
        }

        if ($paymentMethod === 'transfer' && $registration->getPaymentReference() === '') {
            $registration->setPaymentReference(sprintf(
                'HGON-EVENT-%d-%d',
                (int)$registration->getEvent()?->getUid(),
                (int)$registration->getUid()
            ));
        }

        $registration->setPaid(false);
        $this->registrationRepository->update($registration);
    }
}
