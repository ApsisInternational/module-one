APSIS One integration for Magento 2
 ======
 
[![License: MPL 2.0](https://img.shields.io/badge/License-MPL%202.0-brightgreen.svg)](LICENSE)

## Requirements

- Magento 2.4.4+ is compatible from version 2.1.0
  - PHP 8.1+
- Magento 2.2.x, 2.3.x, 2.4.0 - 2.4.3-p3 is compatible up to version 2.0.x
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
- External library(s) dependency
    - [giggsey/libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php)
- APSIS One [Account](https://www.apsis.com/about-us/request-tour)

## Installation

It is recommended to use [composer](https://getcomposer.org) to install the module.

```bash
# Install latest version for Magento 2.4.4+
$ composer require apsis/module-one

# Install latest version for Magento 2.2.x, 2.3.x, 2.4.0 - 2.4.3-p3
$ composer require apsis/module-one:~2.0

# Update to latest version for Magento 2.4.4+
$ composer update apsis/module-one

# Update to latest version for Magento 2.2.x, 2.3.x, 2.4.0 - 2.4.3-p3
$ composer update apsis/module-one:~2.0
```
If you do not use composer, ensure that you also load any dependencies that this module has, such as [giggsey/libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php) and it's own dependencies.

## Support

Full support documentation and setup guides available [here](https://knowledge.apsis.com/hc/en-us/articles/360012942780-Magento).

## Contribution

You are welcome to contribute to our APSIS One integration module for Magento 2. You can either:
- Report a bug: Create a [GitHub issue](https://github.com/ApsisInternational/module-one/issues/new) including detailed description, steps to reproduce issue, Magento version including edition and module version number.
- To request a feature: Please contact us through [support](https://www.apsis.com/services/support)

## Internal Docs
- [Attributes Definition](https://docs.google.com/document/d/1Oyr2-hy-8WzILVUXrec5qZ50mcF2EYCFrGQniF1tYBs/)
- [Events Definition](https://docs.google.com/document/d/1BqLGaFxIfeaqOCMapfjUCy_49u9WxpywAk_1EqNtckg/)  
- [Endpoints Definition](https://docs.google.com/document/d/1vyhZceOLvbzrWtbCIRnTMwEZEJ8D6sANMi0mL7DY-Mw/)
