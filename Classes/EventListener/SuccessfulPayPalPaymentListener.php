<?php

declare(strict_types=1);

namespace HGON\HgonPayment\EventListener;

use DERHANSEN\SfEventMgt\Event\ProcessPaymentSuccessEvent;
use DERHANSEN\SfEventMgt\Service\NotificationService;
use DERHANSEN\SfEventMgt\Utility\MessageType;
use HGON\HgonPayment\Service\EventPayPalService;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Captures successful PayPal payments and sends sf_event_mgt notifications.
 */
final class SuccessfulPayPalPaymentListener
{
    public function __construct(
        private readonly EventPayPalService $eventPayPalService,
        private readonly NotificationService $notificationService,
        private readonly ConfigurationManagerInterface $configurationManager,
    ) {
    }

    /**
     * Confirms the payment at PayPal and marks the registration as paid.
     */
    public function __invoke(ProcessPaymentSuccessEvent $event): void
    {
        if (!$this->eventPayPalService->supports($event->getPaymentMethod())) {
            return;
        }

        $result = $this->eventPayPalService->confirmSuccessfulPayment(
            $event->getRegistration(),
            $event->getGetVariables()
        );

        $variables = $event->getVariables();
        $variables['html'] = $result['html'];
        $variables['title'] = $result['title'] ?? '';
        $variables['message'] = $result['message'] ?? '';
        $variables['event'] = $event->getRegistration()->getEvent();
        $variables['renderSuccessCard'] = (bool)($result['renderSuccessCard'] ?? false);
        $event->setVariables($variables);
        $event->setUpdateRegistration((bool)$result['updateRegistration']);

        if ((bool)($result['sendNotifications'] ?? false)) {
            $settings = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'SfEventMgt',
                'Pipayment'
            );
            $registration = $event->getRegistration();
            $eventModel = $registration->getEvent();
            if ($eventModel !== null) {
                $this->notificationService->sendUserMessage(
                    $event->getRequest(),
                    $eventModel,
                    $registration,
                    $settings,
                    MessageType::REGISTRATION_CONFIRMED
                );
                $this->notificationService->sendAdminMessage(
                    $event->getRequest(),
                    $eventModel,
                    $registration,
                    $settings,
                    MessageType::REGISTRATION_CONFIRMED
                );
            }
        }
    }
}
