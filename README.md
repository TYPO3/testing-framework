![tests](https://github.com/TYPO3/testing-framework/actions/workflows/ci.yml/badge.svg)

# TYPO3 testing framework for core and extensions

A straight and slim set of classes and configuration to test TYPO3 extensions. This framework is
used by the core, too and maintained by the core team as a base to execute unit, functional
and acceptance tests within the TYPO3 extension ecosystem.

## Installation

This framework works on top of a composer based installation.

```
$ composer require --dev typo3/testing-framework
```

## Documentation

Usage examples within core and for extensions can be found in
[TYPO3 explained](https://docs.typo3.org/typo3cms/CoreApiReference/Testing/Index.html).

## Tags and branches

* Branch main is used by core v12, currently not tagged and used as dev-main
  in core for the time being.
* Branch 7 is used by core v11 and tagged as 7.x.x. Extensions can use this to
  run tests with core v11 and prepare for v12 compatibility. Supports PHP 7.4 to 8.1.
* Branch 6 is used by core v10 and tagged as 6.x.x. Extensions can use this to
  run tests with core v10 and v11. Supports PHP 7.2 to 8.1
* Branch 4 is for core v9 and tagged as 4.x.y
* Branch 1 is for core v8 and tagged as 1.x.y
