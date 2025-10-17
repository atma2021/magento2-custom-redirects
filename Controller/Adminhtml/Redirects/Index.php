<?php
/**
 * Copyright © Atma. All rights reserved.
 */
declare(strict_types=1);

namespace Atma\Redirects\Controller\Adminhtml\Redirects;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public function __construct(
        Context $context,
        protected readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Atma_Redirects::custom_redirects');
    }

    public function execute(): \Magento\Framework\View\Result\Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Atma_Redirects::custom_redirects');
        $resultPage->getConfig()->getTitle()->prepend(__('Custom Redirects'));
        
        return $resultPage;
    }
}
