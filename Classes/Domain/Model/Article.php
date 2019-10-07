<?php
namespace HGON\HgonPayment\Domain\Model;

/***
 *
 * This file is part of the "HGON Payment" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Maximilian Fäßler <maximilian@faesslerweb.de>, Fäßler Web UG
 *
 ***/

/**
 * Article
 */
class Article extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
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
     * quantity
     *
     * @var integer
     */
    protected $quantity = 1;

    /**
     * price
     *
     * @var float
     */
    protected $price = 0.0;

    /**
     * vat
     *
     * @var float
     */
    protected $vat = 0.0;

    /**
     * shipping
     *
     * @var float
     */
    protected $shipping = 0.0;

    /**
     * sku
     *
     * @var string
     */
    protected $sku = '';

    /**
     * currency
     *
     * @var string
     */
    protected $currency = 'EUR';

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
     * Returns the quantity
     *
     * @return integer $quantity
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Sets the quantity
     *
     * @param integer $quantity
     * @return void
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * Returns the price
     *
     * @return float $price
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Sets the price
     *
     * @param float $price
     * @return void
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }

    /**
     * Returns the vat
     *
     * @return float $vat
     */
    public function getVat()
    {
        return $this->vat;
    }

    /**
     * Sets the vat
     *
     * @param float $vat
     * @return void
     */
    public function setVat($vat)
    {
        $this->vat = $vat;
    }

    /**
     * Returns the shipping
     *
     * @return float $shipping
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * Sets the shipping
     *
     * @param float $shipping
     * @return void
     */
    public function setShipping($shipping)
    {
        $this->shipping = $shipping;
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
     * Returns the currency
     *
     * @return string $currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Sets the currency
     *
     * @param string $currency
     * @return void
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }
}
