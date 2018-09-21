Paymentsense Module for Magento 2 Open Source
=============================================

Payment module for Magento 2 Open Source (Community Edition), allowing you to take payments via Paymentsense.

Requirements
------------

* Magento 2 Open Source 2.x.x (tested up to 2.2.6)
* PCI-certified server using SSL/TLS (required for Direct and MOTO payment methods)

Installation using Composer
---------------------------

1. Install the Paymentsense module

    ```sh
    $ composer require paymentsense/magento2-module
    ```

2. Enable the Paymentsense module

    ```sh
    $ php bin/magento module:enable Paymentsense_Payments --clear-static-content
    ```

3. Update Magento

    ```sh
    $ php bin/magento setup:upgrade
    ```

4. Deploy the static view files (if needed)
    ```sh
    $ php bin/magento setup:static-content:deploy
    ```

Manual installation 
-------------------

1. Upload the contents of the folder to ```app/code/Paymentsense/Payments/``` in the Magento root folder

2. Enable the Paymentsense module

    ```sh
    $ php bin/magento module:enable Paymentsense_Payments --clear-static-content
    ```

3. Update Magento

    ```sh
    $ php bin/magento setup:upgrade
    ```

4. Deploy the static view files (if needed)
    ```sh
    $ php bin/magento setup:static-content:deploy
    ```

Configuration
-------------

1. Login to the Magento admin panel and go to **Stores** -> **Configuration** -> **Sales** -> **Payment Methods**
2. If the Paymentsense payment methods do not appear in the list of the payment methods, go to 
  **System** -> **Cache Management** and clear the Magento cache by clicking on the **Flush Magento Cache** button
3. Go to **Payment Methods** and click the **Configure** button next to the payment methods **Paymentsense Hosted**, 
  **Paymentsense Direct** or/and **Paymentsense MOTO** to expand the configuration settings
4. Set **Enabled** to **Yes**
5. Set the gateway credentials and pre-shared key where applicable
6. Optionally, set the rest of the settings as per your needs
7. Click the **Save Config** button

Secure Checkout
---------------

The usage of the **Paymentsense Direct** and **Paymentsense MOTO** involves the following additional steps:

1. Make sure SSL/TLS is configured on your PCI-DSS certified server
2. Login to the Magento admin panel
3. Go to **Stores** -> **Configuration** -> **General** -> **Web** 
4. Expand the **Base URLs (Secure)** section 
5. Set **Use Secure URLs on Storefront** and **Use Secure URLs in Admin** to **Yes**
6. Set your **Secure Base URL** 
7. Click the **Save Config** button

Changelog
---------

##### 2.0.2
### Changed
- Order email sent only after successful payment. Emails for failed payments are no longer sent to the customer.


##### 2.0.1
### Added
- Configurable logging
- Code optimisation and rework

### Changed
- Status for orders before payment as "Pending Payment"

### Fixed
- Typos


##### 2.0.0
Initial Release

Support
-------

[devsupport@paymentsense.com](mailto:devsupport@paymentsense.com)