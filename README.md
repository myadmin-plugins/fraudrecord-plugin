# MyAdmin FraudRecord Plugin

[![Tests](https://github.com/detain/myadmin-fraudrecord-plugin/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-fraudrecord-plugin/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-fraudrecord-plugin/version)](https://packagist.org/packages/detain/myadmin-fraudrecord-plugin)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-fraudrecord-plugin/downloads)](https://packagist.org/packages/detain/myadmin-fraudrecord-plugin)
[![License](https://poser.pugx.org/detain/myadmin-fraudrecord-plugin/license)](https://packagist.org/packages/detain/myadmin-fraudrecord-plugin)

A MyAdmin plugin that integrates with the [FraudRecord](https://www.fraudrecord.com/) API to provide fraud detection and reporting capabilities. It allows hosting providers to query customer data against the FraudRecord database, automatically flag or lock accounts that exceed configurable risk thresholds, and report fraudulent activity back to the FraudRecord community.

## Features

- Query the FraudRecord API for fraud scores on customer accounts
- Report fraudulent customers to FraudRecord
- Configurable score thresholds for automatic account locking
- Email notifications for possible fraud detections
- Privacy-preserving hashing of customer data before transmission
- Integrates with the MyAdmin event/hook system via Symfony EventDispatcher

## Installation

```sh
composer require detain/myadmin-fraudrecord-plugin
```

## Configuration

The plugin registers the following settings under **Security & Fraud > FraudRecord Fraud Detection**:

| Setting | Description |
|---------|-------------|
| `fraudrecord_enable` | Enable or disable FraudRecord integration |
| `fraudrecord_api_key` | Your FraudRecord API key |
| `fraudrecord_score_lock` | Score threshold above which accounts are automatically locked |
| `fraudrecord_possible_fraud_score` | Score threshold above which an admin fraud alert email is sent |
| `fraudrecord_reporting` | Enable or disable reporting of fraud back to FraudRecord |

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

## License

This package is licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html) license.
