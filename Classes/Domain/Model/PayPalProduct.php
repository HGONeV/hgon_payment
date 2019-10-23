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
 * Class PayPalProduct
 *
 * @author Maximilian Fäßler <maximilian@faesslerweb.de>
 * @copyright HGON
 * @package HGON_HgonPayment
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PayPalProduct extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{

    /**
     * name
     *
     * @var string
     */
    protected $name = '';

    /**
     * description
     *
     * @var string
     */
    protected $description = '';

    /**
     * type
     *
     * @var string
     */
    protected $type = '';

    /**
     * category
     *
     * @var string
     */
    protected $category = '';

    /**
     * sku
     *
     * @var string
     */
    protected $sku = '';

    /**
     * productId
     *
     * @var string
     */
    protected $productId = '';

    /**
     * Returns the name
     *
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name
     *
     * @param string $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * Returns the type
     *
     * @return string $type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the type
     *
     * @param string $type
     * @return void
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Returns the category
     *
     * @return string $category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Sets the category
     *
     * @param string $category
     * @return void
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * Returns the sku
     *
     * @return string $sku
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * Sets the sku
     *
     * @param string $sku
     * @return void
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
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


}