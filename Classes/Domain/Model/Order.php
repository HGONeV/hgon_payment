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
 * Order
 */
class Order extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
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
     * __construct
     */
    public function __construct()
    {
        //Do not remove the next line: It would break the functionality
        $this->initStorageObjects();
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
}
