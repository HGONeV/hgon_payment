<?php

namespace HGON\HgonPayment\Domain\Repository;

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
 * Class PayPalPlanRepository
 *
 * @author Maximilian Fäßler <faesslerweb@web.de>
 * @copyright HGON
 * @package HGON_HgonPayment
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PayPalPlanRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    public function initializeObject()
    {
        /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
        $querySettings = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class);
        // go for $defaultQuerySettings = $this->createQuery()->getQuerySettings(); if you want to make use of the TS persistence.storagePid with defaultQuerySettings(), see #51529 for details

        // don't add the pid constraint
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }
}
