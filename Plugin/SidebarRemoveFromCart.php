<?php

namespace Hyva\TaggrsDataLayer\Plugin;

use Magento\Checkout\Controller\Sidebar\RemoveItem;
use Magento\Customer\Model\Session;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Store\Model\StoreManagerInterface;
use Taggrs\DataLayer\Api\DataLayerInterface;
use Taggrs\DataLayer\Helper\ProductViewDataHelper;
use Taggrs\DataLayer\Helper\UserDataHelper;
use Magento\Checkout\Model\Session as CheckoutSession;

class SidebarRemoveFromCart implements DataLayerInterface
{

    private Session $session;

    private UserDataHelper $userDataHelper;

    private ProductViewDataHelper $productDataHelper;


    private CheckoutSession $checkoutSession;

    private StoreManagerInterface $storeManager;

    private ?CartItemInterface $removedItem = null;


    public function __construct(
        Session                     $session,
        UserDataHelper              $userDataHelper,
        ProductViewDataHelper       $productDataHelper,
        CheckoutSession             $checkoutSession,
        StoreManagerInterface       $storeManager
    ) {
        $this->session = $session;
        $this->userDataHelper = $userDataHelper;
        $this->productDataHelper = $productDataHelper;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
    }

    public function beforeExecute(RemoveItem $subject)
    {
        $request = $subject->getRequest();
        $quoteItemId = $request->getParam('item_id');

        if ($quoteItemId !== null) {
            foreach ($this->checkoutSession->getQuote()->getItems() as $quoteItem) {
                if ($quoteItemId == $quoteItem->getItemId()) {
                    $this->removedItem = $quoteItem;
                    break;
                }
            }
        }

        if ($this->removedItem !== null) {
            $this->session->setDataLayer($this->getDataLayer());
        }
    }

    public function getEvent(): string
    {
        return 'remove_from_cart';
    }

    public function getEcommerce(): array
    {
        $ecommerce = ['currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode()];

        if ($this->removedItem !== null) {

            $ecommerce['value'] = floatval($this->removedItem->getPriceInclTax()) * $this->removedItem->getQty();

            $item = [
                'item_id' => $this->removedItem->getProduct()->getId(),
                'item_name' => $this->removedItem->getProduct()->getName(),
                'quantity' => $this->removedItem->getQty(),
                'price' => floatval($this->removedItem->getPriceInclTax()),
            ];

            $item = array_merge($item, $this->productDataHelper->getCategoryNamesByProduct($this->removedItem->getProduct()));

            $ecommerce['items'] = [$item];

            $ecommerce['user_data'] = $this->getUserData();
        }

        return $ecommerce;
    }

    public function getUserData(): array
    {
        return $this->userDataHelper->getUserData();
    }

    public function getDataLayer(): array
    {
        return [
            'event' => $this->getEvent(),
            'ecommerce' => $this->getEcommerce(),
        ];
    }
}
