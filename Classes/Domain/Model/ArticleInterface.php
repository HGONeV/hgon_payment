<?php
namespace HGON\HgonPayment\Domain\Model;

    /*
     * This file is part of the FluidTYPO3/Vhs project under GPLv2 or later.
     *
     * For the full copyright and license information, please read the
     * LICENSE.md file that was distributed with this source code.
     */

/**
 * Basic interface which must be implemented by every
 * possible Asset type.
 */
interface ArticleInterface
{
    /**
     * Returns the name
     *
     * @return string $name
     */
    public function getName();

    /**
     * Sets the name
     *
     * @param string $name
     * @return void
     */
    public function setName($name);

    /**
     * Returns the description
     *
     * @return string $description
     */
    public function getDescription();

    /**
     * Sets the description
     *
     * @param string $description
     * @return void
     */
    public function setDescription($description);

    /**
     * Returns the quantity
     *
     * @return integer $quantity
     */
    public function getQuantity();

    /**
     * Sets the quantity
     *
     * @param integer $quantity
     * @return void
     */
    public function setQuantity($quantity);

    /**
     * Returns the price
     *
     * @return float $price
     */
    public function getPrice();

    /**
     * Sets the price
     *
     * @param float $price
     * @return void
     */
    public function setPrice($price);

    /**
     * Returns the vat
     *
     * @return float $vat
     */
    public function getVat();

    /**
     * Sets the vat
     *
     * @param float $vat
     * @return void
     */
    public function setVat($vat);

    /**
     * Returns the shipping
     *
     * @return float $shipping
     */
    public function getShipping();

    /**
     * Sets the shipping
     *
     * @param float $shipping
     * @return void
     */
    public function setShipping($shipping);

    /**
     * Returns the sku
     *
     * @return string $sku
     */
    public function getSku();

    /**
     * Sets the sku
     *
     * @param string $sku
     * @return void
     */
    public function setSku($sku);

    /**
     * Returns the currency
     *
     * @return string $currency
     */
    public function getCurrency();

    /**
     * Sets the currency
     *
     * @param string $currency
     * @return void
     */
    public function setCurrency($currency);

    /**
     * Returns the isDonation
     *
     * @return boolean $isDonation
     */
    public function getIsDonation();

    /**
     * Sets the isDonation
     *
     * @param boolean $isDonation
     * @return void
     */
    public function setIsDonation($isDonation);
}
