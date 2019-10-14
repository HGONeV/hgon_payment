<?php

namespace HGON\HgonPayment\Domain\Model;

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
 * Class Plan
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright HGON
 * @package HGON_HgonPayment
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Plan extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{

    /**
     * title
     *
     * @var string
     */
    protected $title = '';

    /**
     * description
     *
     * @var string
     */
    protected $description = '';

    /**
     * planId
     * the paypal internal plan id
     *
     * @var string
     */
    protected $planId = '';

    /**
     * productId
     * the defined product id. E.g. the internal name of an RkwProject
     *
     * @var string
     */
    protected $productId = '';

    /**
     * status
     * can be "CREATED", "INACTIVE" or "ACTIVE"
     *
     * @var string
     */
    protected $status = '';

    /**
     * data
     * the paypal response array on creating the plan
     *
     * @var string
     */
    protected $data = '';

    /**
     * Returns the title
     *
     * @return string $title
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the title
     *
     * @param string $title
     * @return void
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Returns the description
     *
     * @return string $description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the description
     *
     * @param string $description
     * @return void
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Returns the planId
     *
     * @return string $planId
     */
    public function getPlanId()
    {
        return $this->planId;
    }

    /**
     * Sets the planId
     *
     * @param string $planId
     * @return void
     */
    public function setPlanId($planId)
    {
        $this->planId = $planId;
    }

    /**
     * Returns the productId
     *
     * @return string $productId
     */
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * Sets the productId
     *
     * @param string $productId
     * @return void
     */
    public function setProductId($productId)
    {
        $this->productId = $productId;
    }

    /**
     * Returns the status
     *
     * @return string $status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets the status
     *
     * @param string $status
     * @return void
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Returns the data
     *
     * @return string $data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets the data
     *
     * @param string $data
     * @return void
     */
    public function setData($data)
    {
        $this->data = $data;
    }


}