APSIS One integration for Magento 2
 ======
 
[![License: MPL 2.0](https://img.shields.io/badge/License-MPL%202.0-brightgreen.svg)](LICENSE)

## Requirements

- Magento 2.4.6 and above are compatible from version 2.1.1
  - PHP 8.1+
- Magento 2.4.4 & 2.4.5 is compatible with version 2.1.0
  - PHP 8.1+
- Magento 2.2.x, 2.3.x & 2.4.0 to 2.4.3-p3 is compatible up to version 2.0.8
  - PHP 7.1+
- Magento module(s) dependency 
    - Newsletter
    - Review
    - Sales
    - Wishlist
    - Store
    - Config
    - Backend
    - Cron
    - Customer
    - Catalog
    - Quote
    - Checkout
    - Ui
- APSIS One [Account](https://www.apsis.com/about-us/request-tour)

## Installation

It is recommended to use [composer](https://getcomposer.org) to install the module.

```bash
# Install latest version for Magento 2.4.4 and above
$ composer require apsis/module-one

# Install latest version for Magento 2.2.x, 2.3.x & 2.4.0 to 2.4.3-p3
$ composer require apsis/module-one:~2.0

# Update to latest version for Magento 2.4.4 and above
$ composer update apsis/module-one

# Update to latest version for Magento 2.2.x, 2.3.x, 2.4.0  to 2.4.3-p3
$ composer update apsis/module-one:~2.0
```
If you do not use composer, ensure that you also load any dependencies that this module has.

## Support

Full support documentation and setup guides available [here](https://help.apsis.one/en/).

## Contribution

You are welcome to contribute to our APSIS One integration module for Magento 2. You can either:
- Report a bug: Create a [GitHub issue](https://github.com/ApsisInternational/module-one/issues/new) including detailed description, steps to reproduce issue, Magento version including edition and module version number.
- To request a feature: Please contact us through [support](https://www.apsis.com/services/support)

## Internal Docs
- [Attributes Definition](https://efficy-my.sharepoint.com/:w:/p/aqa/EZDpQ4hY711Ol_2I57QzVJwB6wGu6kWyv54-wS3IpZKKMw?e=0xMYn9)
- [Events Definition](https://efficy-my.sharepoint.com/:w:/p/aqa/ESr18U14JsdEgRSovPxb5S4BeCVtX4lYjCFjV1rJ53mHZg?e=rIR1e4)
- [Abandoned Cart Definition](https://efficy-my.sharepoint.com/:w:/p/aqa/EXcCpN1BtaJDmwL2W2mN_Y4Bu-RY9PuF_nMcXYwHDB99EQ?e=oUDdGn)
