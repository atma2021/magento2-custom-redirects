<?php
/**
 * Copyright © Atma. All rights reserved.
 */
declare(strict_types=1);

namespace Atma\Redirects\Controller\Adminhtml\Redirects;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Magento\Store\Model\StoreManagerInterface;

class Upload extends Action
{
    public function __construct(
        Context $context,
        protected readonly Filesystem $filesystem,
        protected readonly Csv $csv,
        protected readonly UrlRewriteFactory $urlRewriteFactory,
        protected readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Atma_Redirects::custom_redirects');
    }

    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/index');

        try {
            // Increase execution time for large CSV files
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M');

            // Get uploaded file
            $files = $this->getRequest()->getFiles();

            if (!isset($files['csv_file']) || !$files['csv_file']['tmp_name']) {
                throw new \Exception(__('Please upload a CSV file.'));
            }

            $file = $files['csv_file'];

            // Validate file extension
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($fileExtension !== 'csv') {
                throw new \Exception(__('Only CSV files are allowed.'));
            }

            // Validate file size (max 50MB for large CSV files)
            if ($file['size'] > 50 * 1024 * 1024) {
                throw new \Exception(__('File size exceeds 50MB limit.'));
            }

            // Create custom redirects directory
            $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $customRedirectsPath = 'custom-redirects';

            if (!$varDir->isDirectory($customRedirectsPath)) {
                $varDir->create($customRedirectsPath);
            }

            // Copy uploaded file to var/custom-redirects/
            $fileName = 'redirects_' . date('Y-m-d_H-i-s') . '.csv';
            $destination = $varDir->getAbsolutePath($customRedirectsPath . '/' . $fileName);

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new \Exception(__('Failed to save uploaded file.'));
            }

            // Set CSV delimiter
            $this->csv->setDelimiter('|');

            // Read CSV file
            $csvData = $this->csv->getData($destination);

            if (empty($csvData)) {
                throw new \Exception(__('The CSV file is empty.'));
            }

            // Get headers
            $headers = array_shift($csvData);

            // Remove any BOM characters from first header
            if (!empty($headers[0])) {
                $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
            }

            // Trim headers
            $headers = array_map('trim', $headers);

            // Validate that 'Url' column exists
            $urlColumnIndex = array_search('Url', $headers);
            if ($urlColumnIndex === false) {
                throw new \Exception(__('The CSV file must contain a column named "Url".'));
            }

            // Get store and set homepage redirect
            $store = $this->storeManager->getStore();
            $baseUrl = $store->getBaseUrl();
            
            // Use empty string for homepage - Magento will redirect to store's base URL
            // This is not editable in admin but works correctly
            $homepageUrl = '';

            // Description for the redirects
            $description = 'This was created by Atma_Redirects module on ' . date('Y-m-d H:i:s');

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            // Process each row
            foreach ($csvData as $rowIndex => $row) {
                try {
                    // Skip empty rows
                    if (empty($row) || !isset($row[$urlColumnIndex])) {
                        continue;
                    }

                    $requestPath = trim($row[$urlColumnIndex]);

                    // Skip empty URLs
                    if (empty($requestPath)) {
                        continue;
                    }

                    // Parse and clean the URL
                    // If it's a full URL, parse it to extract path only (no query params)
                    if (preg_match('#^https?://#i', $requestPath)) {
                        $parsedUrl = parse_url($requestPath);
                        $requestPath = $parsedUrl['path'] ?? '';
                    }

                    // Remove query string if present (Magento URL rewrites don't use query params in request_path)
                    if (strpos($requestPath, '?') !== false) {
                        $requestPath = substr($requestPath, 0, strpos($requestPath, '?'));
                    }

                    // Clean leading/trailing slashes from path
                    $requestPath = trim($requestPath, '/');

                    // If still empty after cleaning, skip
                    if (empty($requestPath)) {
                        continue;
                    }

                    // Skip if URL is already homepage
                    if ($requestPath === '' || $requestPath === '/') {
                        continue;
                    }

                    // Check if redirect already exists
                    $existingRewrite = $this->urlRewriteFactory->create()
                        ->getCollection()
                        ->addFieldToFilter('request_path', $requestPath)
                        ->addFieldToFilter('store_id', $store->getId())
                        ->getFirstItem();

                    if ($existingRewrite->getId()) {
                        // Update existing redirect
                        $existingRewrite->setTargetPath($homepageUrl);
                        $existingRewrite->setRedirectType(301);
                        $existingRewrite->setDescription($description);
                        $existingRewrite->save();
                    } else {
                        // Create new redirect
                        $urlRewrite = $this->urlRewriteFactory->create();
                        $urlRewrite->setStoreId($store->getId());
                        $urlRewrite->setRequestPath($requestPath);
                        $urlRewrite->setTargetPath($homepageUrl);
                        $urlRewrite->setRedirectType(301);
                        $urlRewrite->setIsAutogenerated(0);
                        $urlRewrite->setDescription($description);
                        $urlRewrite->save();
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = __('Row %1: %2', $rowIndex + 2, $e->getMessage());
                }
            }

            // Add success message
            if ($successCount > 0) {
                $this->messageManager->addSuccessMessage(
                    __('Successfully created/updated %1 redirect(s).', $successCount)
                );
            }

            // Add error messages
            if ($errorCount > 0) {
                $this->messageManager->addWarningMessage(
                    __('Failed to process %1 redirect(s).', $errorCount)
                );
                foreach (array_slice($errors, 0, 5) as $error) {
                    $this->messageManager->addErrorMessage($error);
                }
            }

            if ($successCount === 0 && $errorCount === 0) {
                $this->messageManager->addNoticeMessage(
                    __('No valid URLs found in the CSV file.')
                );
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect;
    }
}
