<?php
/**
 * Copyright © Atma. All rights reserved.
 */
declare(strict_types=1);

namespace Atma\Redirects\Block\Adminhtml\Redirects;

use Magento\Backend\Block\Template;

class Upload extends Template
{
    public function getFormAction(): string
    {
        return $this->getUrl('*/*/upload');
    }
}
