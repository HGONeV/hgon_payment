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
 * Basket
 */
class Basket extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * decription
     *
     * @var string
     */
    protected $decription = '';

    /**
     * softDescriptor
     *
     * @var string
     */
    protected $softDescriptor = '';

    /**
     * invoiceNumber
     *
     * @var string
     */
    protected $invoiceNumber = '';

    /**
     * article
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\HGON\HgonPayment\Domain\Model\Article>
     * @cascade remove
     */
    protected $article = null;

    /**
     * paymentData
     *
     * @var array
     */
    protected $paymentData = [];

    /**
     * __construct
     */
    public function __construct()
    {
        //Do not remove the next line: It would break the functionality
        $this->initStorageObjects();

        // create unique invoice number
        $this->setInvoiceNumber(uniqid('hgon_'));
    }

    /**
     * Initializes all ObjectStorage properties
     * Do not modify this method!
     * It will be rewritten on each save in the extension builder
     * You may modify the constructor of this class instead
     *
     * @return void
     */
    protected function initStorageObjects()
    {
        $this->article = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    /**
     * Returns the decription
     *
     * @return string $decription
     */
    public function getDecription()
    {
        return $this->decription;
    }

    /**
     * Sets the decription
     *
     * @param string $decription
     * @return void
     */
    public function setDecription($decription)
    {
        $this->decription = $decription;
    }

    /**
     * Returns the softDescriptor
     *
     * @return string $softDescriptor
     */
    public function getSoftDescriptor()
    {
        return $this->softDescriptor;
    }

    /**
     * Sets the softDescriptor
     *
     * @param string $softDescriptor
     * @return void
     */
    public function setSoftDescriptor($softDescriptor)
    {
        $this->softDescriptor = $softDescriptor;
    }

    /**
     * Returns the invoiceNumber
     *
     * @return string $invoiceNumber
     */
    public function getInvoiceNumber()
    {
        return $this->invoiceNumber;
    }

    /**
     * Sets the invoiceNumber
     *
     * @param string $invoiceNumber
     * @return void
     */
    public function setInvoiceNumber($invoiceNumber)
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    /**
     * Adds a Article
     *
     * @param \HGON\HgonPayment\Domain\Model\Article $article
     * @return void
     */
    public function addArticle(\HGON\HgonPayment\Domain\Model\Article $article)
    {
        $this->article->attach($article);
    }

    /**
     * Removes a Article
     *
     * @param \HGON\HgonPayment\Domain\Model\Article $articleToRemove The Article to be removed
     * @return void
     */
    public function removeArticle(\HGON\HgonPayment\Domain\Model\Article $articleToRemove)
    {
        $this->article->detach($articleToRemove);
    }

    /**
     * Returns the article
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\HGON\HgonPayment\Domain\Model\Article> $article
     */
    public function getArticle()
    {
        return $this->article;
    }

    /**
     * Sets the article
     *
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\HGON\HgonPayment\Domain\Model\Article> $article
     * @return void
     */
    public function setArticle(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $article)
    {
        $this->article = $article;
    }

    /**
     * Returns the paymentData
     *
     * @return array $paymentData
     */
    public function getPaymentData()
    {
        return $this->paymentData;
    }

    /**
     * Sets the paymentData
     *
     * @param array $paymentData
     * @return void
     */
    public function setPaymentData($paymentData)
    {
        $this->paymentData = $paymentData;
    }

    /**
     * Add prices of all articles
     *
     * @return float
     */
    public function getSubTotal()
    {
        $subTotal = 0.00;
        /** @var \HGON\HgonPayment\Domain\Model\Article $article */
        foreach ($this->getArticle() as $article) {
            $subTotal += $article->getPrice();
        }
        return number_format($subTotal, 2, '.', ',');
    }

    /**
     * Add vat of all articles
     *
     * @return float
     */
    public function getTaxTotal()
    {
        $vatTotal = 0.00;
        /** @var \HGON\HgonPayment\Domain\Model\Article $article */
        foreach ($this->getArticle() as $article) {
            $vatTotal += $article->getVat();
        }
        return number_format($vatTotal, 2, '.', ',');
    }

    /**
     * Add total of all articles
     *
     * @return float
     */
    public function getTotal()
    {
        return number_format($this->getSubTotal() + $this->getTaxTotal(), 2, '.', ',');
    }

    /**
     * Use highest
     *
     * @return float
     */
    public function getShippingCosts()
    {
        $shippingCostsArray = [];
        /** @var \HGON\HgonPayment\Domain\Model\Article $article */
        foreach ($this->getArticle() as $article) {
            $shippingCostsArray[] = $article->getShipping();
        }
        return number_format(max($shippingCostsArray), 2, '.', ',');
    }

    /**
     * Create an array for payPal
     *
     * @return array
     */
    public function getArticleArrayForPayPal()
    {
        $articleList = [];
        /** @var \HGON\HgonPayment\Domain\Model\Article $article */
        foreach ($this->getArticle() as $article) {
            $item = [];
            $item['name'] = $article->getName();
            $item['quantity'] = $article->getQuantity();
            $item['price'] = $article->getPrice();
            $item['sku'] = $article->getSku() ? $article->getSku() : 'article' . $article->getUid();
            $item['currency'] = $article->getCurrency() ? $article->getCurrency() : 'EUR';
            $articleList[] = $item;
        }
        return $articleList;
    }

}
