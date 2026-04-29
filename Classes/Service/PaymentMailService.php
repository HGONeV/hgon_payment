<?php

namespace HGON\HgonPayment\Service;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Beuser\Domain\Model\BackendUser;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentMailService
{
    public function __construct(
        private readonly ?ViewFactoryInterface $viewFactory = null,
        private readonly ?MailerInterface $mailer = null,
    ) {
    }

    public function confirmPayPalUser(array $paymentData): void
    {
        $recipientEmail = (string)($paymentData['customer']['email'] ?? '');
        if (!GeneralUtility::validEmail($recipientEmail)) {
            return;
        }

        $customerName = $this->splitName((string)($paymentData['customer']['name'] ?? ''));
        $variables = [
            'paymentData' => $paymentData,
            'language' => 'de',
            'firstName' => $customerName['firstName'],
            'lastName' => $customerName['lastName'],
            'fullName' => trim($customerName['firstName'] . ' ' . $customerName['lastName']),
            'admin' => false,
        ];

        $this->send(
            $recipientEmail,
            (string)($paymentData['customer']['name'] ?? ''),
            $this->translate('paymentMailService.confirmationPayPalUser.subject'),
            'Email/ConfirmationPayPalUser',
            $variables
        );
    }

    public function confirmPayPalAdmin(BackendUser $backendUser, array $paymentData): void
    {
        $recipientEmail = $backendUser->getEmail();
        if (!GeneralUtility::validEmail($recipientEmail)) {
            return;
        }

        $isDonation = (int)($paymentData['payment']['isDonation'] ?? 0);
        $subject = $this->translate('paymentMailService.confirmationPayPalAdmin.subject.' . $isDonation)
            ?: $this->translate('paymentMailService.confirmationPayPalAdmin.subject');

        $variables = [
            'paymentData' => $paymentData,
            'language' => 'de',
            'fullName' => $backendUser->getRealName() ?: $backendUser->getEmail(),
            'admin' => true,
        ];

        $email = $this->buildEmail(
            $recipientEmail,
            $backendUser->getRealName(),
            $subject,
            'Email/ConfirmationPayPalAdmin',
            $variables
        );

        $customerEmail = (string)($paymentData['customer']['email'] ?? '');
        if (GeneralUtility::validEmail($customerEmail)) {
            $email->replyTo(new Address($customerEmail, (string)($paymentData['customer']['name'] ?? '')));
        }

        $this->getMailer()->send($email);
    }

    private function send(string $recipientEmail, string $recipientName, string $subject, string $templateName, array $variables): void
    {
        $this->getMailer()->send($this->buildEmail($recipientEmail, $recipientName, $subject, $templateName, $variables));
    }

    private function buildEmail(string $recipientEmail, string $recipientName, string $subject, string $templateName, array $variables): Email
    {
        return (new Email())
            ->from(new Address(MailUtility::getSystemFromAddress(), MailUtility::getSystemFromName() ?? ''))
            ->to(new Address($recipientEmail, $recipientName))
            ->subject($subject)
            ->html($this->render($templateName, $variables + ['mailType' => 'Html']))
            ->text(trim($this->render($templateName, $variables + ['mailType' => 'Plaintext'])));
    }

    private function render(string $templateName, array $variables): string
    {
        $view = $this->getViewFactory()->create(new ViewFactoryData(
            templateRootPaths: ['EXT:hgon_payment/Resources/Private/Templates/'],
            partialRootPaths: [
                'EXT:hgon_payment/Resources/Private/Partials/',
                'EXT:hgon_template/Resources/Private/Extension/HgonTemplate/Partials/',
            ],
            layoutRootPaths: ['EXT:hgon_template/Resources/Private/Extension/HgonTemplate/Layouts/'],
        ));
        $view->assignMultiple($variables);

        return $view->render($templateName);
    }

    private function translate(string $key): string
    {
        return LocalizationUtility::translate($key, 'HgonPayment') ?: '';
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [
            'firstName' => $parts[0] ?? '',
            'lastName' => $parts[1] ?? '',
        ];
    }

    private function getViewFactory(): ViewFactoryInterface
    {
        return $this->viewFactory ?? GeneralUtility::makeInstance(ViewFactoryInterface::class);
    }

    private function getMailer(): MailerInterface
    {
        return $this->mailer ?? GeneralUtility::makeInstance(MailerInterface::class);
    }
}
