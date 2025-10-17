# Atma_Redirects Module

## Overview
This module allows you to upload CSV files containing URLs that should be redirected to the homepage with permanent (301) redirects.

## Features
- Admin menu item under **Marketing > SEO & Search > Custom Redirects**
- CSV file upload form with validation
- Creates 301 (permanent) redirects to homepage
- Stores uploaded CSV files in `var/custom-redirects/`

## Installation

1. Enable the module:
```bash
php bin/magento module:enable Atma_Redirects
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

## Usage

1. Navigate to **Marketing > SEO & Search > Custom Redirects** in the admin panel
2. Prepare your CSV file following these requirements:
   - **Delimiter**: Use `|` (pipe) as the field delimiter
   - **String Delimiter**: Use `"` (double quotes)
   - **Column Name**: Must be `Url`
   - **File Format**: Only `.csv` files are accepted
   
3. Example CSV format:
```csv
Url
old-page-1.html
old-page-2.html
category/old-category.html
```

4. Click "Upload CSV File" and select your file
5. The module will process the file and create permanent redirects to the homepage

## CSV File Requirements

- **Delimiter**: `|`
- **String Delimiter**: `"`
- **Required Column**: `Url`
- **File Extension**: `.csv` only
- **Max File Size**: 50MB
- **Performance**: Optimized for files with 1000+ URLs (5 minute timeout, 512MB memory limit)

## Technical Details

### Module Structure
- **Registration**: `registration.php`
- **Configuration**: `etc/module.xml`
- **Admin Menu**: `etc/adminhtml/menu.xml`
- **ACL**: `etc/acl.xml`
- **Routes**: `etc/adminhtml/routes.xml`
- **Controllers**: 
  - `Controller/Adminhtml/Redirects/Index.php` - Displays the form
  - `Controller/Adminhtml/Redirects/Upload.php` - Processes CSV upload
- **Block**: `Block/Adminhtml/Redirects/Upload.php`
- **Template**: `view/adminhtml/templates/redirects/upload.phtml`
- **Layout**: `view/adminhtml/layout/atma_redirects_redirects_index.xml`

### How It Works
1. User uploads CSV file through admin interface
2. File is validated for format and extension
3. File is saved to `var/custom-redirects/` directory with timestamp
4. CSV is parsed using `|` delimiter
5. For each URL in the 'Url' column:
   - Creates a new URL rewrite or updates existing one
   - Sets redirect type to 301 (permanent)
   - Points to homepage (`/`)
6. Success/error messages are displayed to the user

## Notes
- Existing redirects with the same request path will be updated
- Empty rows and URLs are skipped
- Base URLs are automatically cleaned from the input
- All redirects are store-specific

## Copyright
Copyright © Atma. All rights reserved.
