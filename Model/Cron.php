<?php

namespace Dotdigitalgroup\Email\Model;

class Cron
{

    public $contactFactory;
    protected $_automationFactory;
    protected $_importerFactory;
    protected $_catalogFactory;
    protected $_subscriberFactory;
    protected $_guestFactory;
    protected $_wishlistFactory;
    protected $_orderFactory;
    protected $_reviewFactory;
    protected $_quoteFactory;
    protected $_syncOrderFactory;
    protected $_campaignFactory;
    protected $_helper;
    protected $_fileHelper;
    protected $_importerResource;


    /**
     * Cron constructor.
     *
     * @param Sync\CampaignFactory $campaignFactory
     * @param Sync\OrderFactory $syncOrderFactory
     * @param Sales\QuoteFactory $quoteFactory
     * @param Sync\ReviewFactory $reviewFactory
     * @param Sales\OrderFactory $orderFactory
     * @param Sync\WishlistFactory $wishlistFactory
     * @param Customer\GuestFactory $guestFactory
     * @param Newsletter\SubscriberFactory $subscriberFactory
     * @param Sync\CatalogFactory $catalogFactorty
     * @param Sync\AutomationFactory $automationFactory
     * @param Apiconnector\ContactFactory $contact
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     * @param \Dotdigitalgroup\Email\Helper\File $fileHelper
     * @param Resource\Importer $importerResource
     */
    public function __construct(
        \Dotdigitalgroup\Email\Model\Sync\CampaignFactory $campaignFactory,
        \Dotdigitalgroup\Email\Model\Sync\OrderFactory $syncOrderFactory,
        \Dotdigitalgroup\Email\Model\Sales\QuoteFactory $quoteFactory,
        \Dotdigitalgroup\Email\Model\Sync\ReviewFactory $reviewFactory,
        \Dotdigitalgroup\Email\Model\Sales\OrderFactory $orderFactory,
        \Dotdigitalgroup\Email\Model\Sync\WishlistFactory $wishlistFactory,
        \Dotdigitalgroup\Email\Model\Customer\GuestFactory $guestFactory,
        \Dotdigitalgroup\Email\Model\Newsletter\SubscriberFactory $subscriberFactory,
        \Dotdigitalgroup\Email\Model\Sync\CatalogFactory $catalogFactorty,
        \Dotdigitalgroup\Email\Model\ImporterFactory $importerFactory,
        \Dotdigitalgroup\Email\Model\Sync\AutomationFactory $automationFactory,
        \Dotdigitalgroup\Email\Model\Apiconnector\ContactFactory $contact,
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Dotdigitalgroup\Email\Helper\File $fileHelper,
        \Dotdigitalgroup\Email\Model\Resource\Importer $importerResource
    ) {
        $this->_campaignFactory = $campaignFactory;
        $this->_syncOrderFactory = $syncOrderFactory;
        $this->_quoteFactory = $quoteFactory;
        $this->_reviewFactory = $reviewFactory;
        $this->_orderFactory = $orderFactory;
        $this->_wishlistFactory = $wishlistFactory;
        $this->_guestFactory = $guestFactory;
        $this->_subscriberFactory = $subscriberFactory;
        $this->_catalogFactory = $catalogFactorty;
        $this->_importerFactory    = $importerFactory;
        $this->_automationFactory = $automationFactory;
        $this->contactFactory = $contact;
        $this->_helper = $helper;
        $this->_fileHelper = $fileHelper;
        $this->_importerResource = $importerResource;

    }

    /**
     * CRON FOR CONTACTS SYNC
     *
     * @return mixed
     */
    public function contactSync()
    {

        //run the sync for contacts
        $result = $this->contactFactory->create()->sync();
        //run subscribers and guests sync
        $subscriberResult = $this->subscribersAndGuestSync();

        if (isset($subscriberResult['message']) && isset($result['message'])) {
            $result['message'] = $result['message'] . ' - '
                . $subscriberResult['message'];
        }

        return $result;
    }

    /**
     * CRON FOR SUBSCRIBERS AND GUEST CONTACTS
     */
    public function subscribersAndGuestSync()
    {
        //sync subscribers
        $subscriberModel = $this->_subscriberFactory->create();
        $result = $subscriberModel->sync();

        //sync guests
        $this->_guestFactory->create()->sync();

        return $result;
    }

    /**
     * CRON FOR CATALOG SYNC
     */
    public function catalogSync()
    {
        $result = $this->_catalogFactory->create()
            ->sync();

        return $result;
    }

    /**
     * CRON FOR EMAIL IMPORTER PROCESSOR
     */
    public function emailImporter()
    {
        return $this->_importerFactory->create()->processQueue();
    }

    /**
     * CRON FOR SYNC REVIEWS and REGISTER ORDER REVIEW CAMPAIGNS
     */
    public function reviewsAndWishlist()
    {
        //sync reviews
        $this->reviewSync();
        //sync wishlist
        $this->_wishlistFactory->create()->sync();
    }

    /**
     * review sync
     */
    public function reviewSync()
    {
        //find orders to review and register campaign
        $this->_orderFactory->create()->createReviewCampaigns();
        //sync reviews
        $result = $this->_reviewFactory->create()->sync();

        return $result;
    }


    /**
     * CRON FOR ABANDONED CARTS
     */
    public function abandonedCarts()
    {
        $this->_quoteFactory->create()->proccessAbandonedCarts();
    }

    /**
     * CRON FOR AUTOMATION
     */
    public function syncAutomation()
    {
        $this->_automationFactory->create()->sync();

    }

    /**
     * Send email campaigns.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendCampaigns()
    {
        $this->_campaignFactory->create()->sendCampaigns();
    }


    /**
     * CRON FOR ORDER TRANSACTIONAL DATA
     */
    public function orderSync()
    {
        // send order
        $orderResult = $this->_syncOrderFactory->create()->sync();

        return $orderResult;
    }

    public function cleaning()
    {
        //Clean tables
        $tables = array(
            'automation' => 'email_automation',
            'importer' => 'email_importer',
            'campaign' => 'email_campaign'
        );
        $message = 'Cleaning cron job result :';
        foreach ($tables as $key => $table) {
            $result = $this->_importerResource->cleanup($table);
            $message .= " $result records removed from $key .";
        }
        $archivedFolder = $this->_fileHelper->getArchiveFolder();
        $result         = $this->_fileHelper->deleteDir($archivedFolder);
        $message .= ' Deleting archived folder result : ' . $result;
        $this->_helper->log($message);

        return $message;
    }
}