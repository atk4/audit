# Agile Data - Audit Add-on

This extension for Agile Data implements advanced logging capabilities as well as some elements of
Event Sourcing.

## Documentation

https://github.com/atk4/audit/blob/develop/docs/index.md

## Real Usage Example

https://github.com/atk4/audit/blob/develop/docs/full-example.md

## Installation

Add the following inside your `composer.json` file:

``` json
{
    "require": {
        "atk4/audit": "dev-develop"
    },
    "repositories": [
      {
          "type": "package",
          "package": {
              "name": "atk4/audit",
              "version": "dev-develop",
              "type": "package",
              "source": {
                  "url": "git@github.com:atk4/audit.git",
                  "type": "git",
                  "reference": "develop"
              }
          }
      }
    ],
}
```


``` console
composer require atk4/audit
```

## Current Status

Audit extension is currently under development.

