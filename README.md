Paymentsense Module for Magento 2 Open Source
=============================================

Payment module for Magento 2 Open Source (Community Edition), allowing you to take payments via Paymentsense.

Requirements
------------

* Magento Open Source version 2.3.x or 2.4.x (tested up to 2.4.1)
* PCI-certified server using SSL/TLS (required for Direct and MOTO payment methods)
* Open outbound port 4430 in order to use the Direct and MOTO payment methods and for performing cross reference transactions (Collection, Refund and Void)

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

### 2.4.0
##### Added
- Support of Magento 2.4
- gw3 gateway entry point
- Billing address to the payment method selecting page (Paymentsense Hosted)
- Code optimisation and rework


### 2.3.2
##### Added
- HMACSHA256 and HMACSHA512 hash methods (Paymentsense Hosted)
- Filter for characters not supported by the HPF (Paymentsense Hosted)
- Length restriction of fields sent to the HPF (Paymentsense Hosted)


### 2.3.1
##### Added
- Option for charging in the base currency
- Code optimisation and rework

##### Fixed
- Issue raising the fraud flag in a multi-currency environment


### 2.3.0
##### Added
- File checksums to the module information feature

##### Removed
- MD5 hash method (Paymentsense Hosted)
- Support of Magento 2.2


### 2.0.8
##### Added
- Support of all currencies as per the ISO 4217


### 2.0.7
##### Added
- System time check


### 2.0.6
##### Added
- Gateway settings check

##### Changed
- URL of the extended module information feature
- Output of the extended module information feature


### 2.0.5
##### Added
- SERVER result delivery method (Paymentsense Hosted)

##### Fixed
- Switching to the next gateway entry point when an unexpected response from the gateway is received


### 2.0.4
##### Added
- Extended module information feature
- Payment method status on the Payments Methods configuration page
- Gateway connection status on the Payments Methods configuration page
- "Port 4430 is NOT open on my server (safe mode with cross reference transactions disabled)" configuration setting disabling the cross reference transactions (Paymentsense Hosted)

##### Removed
- gw3 gateway entry point


### 2.0.3
##### Added
- Support of Magento 2.3.x. CSRF protection compliance
- Module information reporting feature

##### Changed
- Logos


### 2.0.2
##### Changed
- Order email sent only after successful payment. Emails for failed payments are no longer sent to the customer.


### 2.0.1
##### Added
- Configurable logging
- Code optimisation and rework

##### Changed
- Status for orders before payment as "Pending Payment"

##### Fixed
- Typos


### 2.0.0
Initial Release

Support
-------

[devsupport@paymentsense.com](mailto:devsupport@paymentsense.com)
