<?php

namespace HGON\HgonPayment\Service;

use Konafets\Typo3Debugbar\Overrides\DebuggerUtility;
use \RKW\RkwBasics\Helper\Common;
use \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use RKW\RkwEvents\Helper\DivUtility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * RkwMailService
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright HGON
 * @package Hgon_HgonPayment
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class RkwMailService implements \TYPO3\CMS\Core\SingletonInterface
{
    
    /**
     * Handles confirm mail for user
     *
     * @param \RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser
     * @param array $paymentData
     * @return void
     * @throws \RKW\RkwMailer\Service\MailException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function confirmPayPalUser(\RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser, $paymentData)
    {
        // send confirmation
        $this->userMail($frontendUser, $paymentData, 'confirmation');
    }


    /**
     * Handles confirm mail for admin
     *
     * @param \RKW\RkwRegistration\Domain\Model\BackendUser|array $backendUser
     * @param array $paymentData
     * @param \RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser
     * @return void
     * @throws \RKW\RkwMailer\Service\MailException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function confirmPayPalAdmin($backendUser, $paymentData, $frontendUser)
    {
        $this->adminMail($backendUser, $paymentData, 'confirmation', $frontendUser);
    }

    /**
     * Sends an E-Mail to a Frontend-User
     *
     * @param \RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser
     * @param array $paymentData
     * @param string $action
     * @throws \RKW\RkwMailer\Service\MailException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    protected function userMail(\RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser, $paymentData, $action = 'confirmation')
    {
        // get settings
        $settings = $this->getSettings(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        $settingsDefault = $this->getSettings();

        if ($settings['view']['templateRootPaths']) {

            /** @var \RKW\RkwMailer\Service\MailService $mailService */
            $mailService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('RKW\\RkwMailer\\Service\\MailService');

            // send new user an email with token
            $mailService->setTo($frontendUser, array(
                'marker' => array(
                    'paymentData'  => $paymentData,
                    'frontendUser' => $frontendUser,
                    'pageUid'      => intval($GLOBALS['TSFE']->id),
                    'showPid'      => intval($settingsDefault['showPid']),
                    'uniqueKey'    => uniqid(),
                    'currentTime'  => time(),
                    'surveyPid'    => intval($settingsDefault['surveyPid']),
                ),
            ));

            $mailService->getQueueMail()->setSubject(
                \RKW\RkwMailer\Helper\FrontendLocalization::translate(
                    'rkwMailService.' . strtolower($action) . 'PayPalUser.subject',
                    'hgon_payment',
                    null,
                    $frontendUser->getTxRkwregistrationLanguageKey()
                )
            );

            $mailService->getQueueMail()->addTemplatePaths($settings['view']['templateRootPaths']);
            $mailService->getQueueMail()->addPartialPaths($settings['view']['partialRootPaths']);

            $mailService->getQueueMail()->setPlaintextTemplate('Email/' . ucfirst(strtolower($action)) . 'PayPalUser');
            $mailService->getQueueMail()->setHtmlTemplate('Email/' . ucfirst(strtolower($action)) . 'PayPalUser');

            $mailService->send();
        }
    }


    /**
     * Sends an E-Mail to an Admin
     *
     * @param \RKW\RkwRegistration\Domain\Model\BackendUser|array $backendUser
     * @param array $paymentData
     * @param string $action
     * @param \RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser
     * @throws \RKW\RkwMailer\Service\MailException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    protected function adminMail($backendUser, $paymentData, $action = 'confirmation', \RKW\RkwRegistration\Domain\Model\FrontendUser $frontendUser = null)
    {
        // get settings
        $settings = $this->getSettings(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        $settingsDefault = $this->getSettings();

        $recipients = array();
        if (is_array($backendUser)) {
            $recipients = $backendUser;
        } else {
            $recipients[] = $backendUser;
        }

        if ($settings['view']['templateRootPaths']) {

            /** @var \RKW\RkwMailer\Service\MailService $mailService */
            $mailService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('RKW\\RkwMailer\\Service\\MailService');

            foreach ($recipients as $recipient) {

                if (
                    $recipient instanceof \RKW\RkwRegistration\Domain\Model\BackendUser
                    && $recipient->getEmail()
                ) {

                    $language = $recipient->getLang();
                    if ($language instanceof \SJBR\StaticInfoTables\Domain\Model\Language) {
                        $language = $language->getTypo3Code();
                    }

                    $name = '';
                    if ($recipient instanceof \RKW\RkwEvents\Domain\Model\BackendUser) {
                        $name = $recipient->getRealName();
                    }

                    // send new user an email with token
                    $mailService->setTo($recipient, array(
                        'marker'  => array(
                            'paymentData'  => $paymentData,
                            'admin'        => $recipient,
                            'frontendUser' => $frontendUser,
                            'pageUid'      => intval($GLOBALS['TSFE']->id),
                            'showPid'      => intval($settingsDefault['showPid']),
                            'fullName'     => $backendUser->getRealName(),
                            'language'     => $language,
                        ),
                        'subject' => \RKW\RkwMailer\Helper\FrontendLocalization::translate(
                            'rkwMailService.' . strtolower($action) . 'PayPalAdmin.subject.' . $paymentData['payment']['isDonation'],
                            'hgon_payment',
                            null,
                            $recipient->getLang()
                        ),
                    ));
                }
            }

            if (
                ($frontendUser)
                && ($frontendUser->getEmail())
            ) {
                $mailService->getQueueMail()->setReplyAddress($frontendUser->getEmail());
            }

            $mailService->getQueueMail()->setSubject(
                \RKW\RkwMailer\Helper\FrontendLocalization::translate(
                    'rkwMailService.' . strtolower($action) . 'PayPalAdmin.subject.' . $paymentData['payment']['isDonation'],
                    'hgon_payment',
                    null,
                    'de'
                )
            );

            $mailService->getQueueMail()->addTemplatePaths($settings['view']['templateRootPaths']);
            $mailService->getQueueMail()->addPartialPaths($settings['view']['partialRootPaths']);

            $mailService->getQueueMail()->setPlaintextTemplate('Email/' . ucfirst(strtolower($action)) . 'PayPalAdmin');
            $mailService->getQueueMail()->setHtmlTemplate('Email/' . ucfirst(strtolower($action)) . 'PayPalAdmin');

            if (count($mailService->getTo())) {
                $mailService->send();
            }
        }
    }


    /**
     * Returns TYPO3 settings
     *
     * @param string $which Which type of settings will be loaded
     * @return array
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    protected function getSettings($which = ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS)
    {
        return Common::getTyposcriptConfiguration('Hgonpayment', $which);
        //===
    }
}
