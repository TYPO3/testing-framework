![tests](https://github.com/TYPO3/testing-framework/actions/workflows/ci.yml/badge.svg)

# TYPO3 testing framework for core and extensions

A straight and slim set of classes and configuration to test TYPO3 extensions. This framework is
used by the core, too and maintained by the core team as a base to execute unit and functional
tests within the TYPO3 extension ecosystem.

## Installation

This framework works on top of a Composer based installation:

```bash
composer require --dev "typo3/testing-framework":"^10.0"
```

## Version matrix

Which version to use depends on the TYPO3 and PHP versions to be supported.

| Branch | testing-framework | TYPO3            | PHP                               | PHPUnit         |
|--------|-------------------|------------------|-----------------------------------|-----------------|
| main   | 10.x.x            | v14, v15 (main)  | 8.2, 8.3, 8.4, 8.5                | ^11, ^12, ^13   |
| 9      | 9.x.x             | v13, v14         | 8.2, 8.3, 8.4, 8.5                | ^11, ^12, ^13   |
| 8      | 8.x.x             | v12, v13         | 8.1, 8.2, 8.3, (8.4)              | ^10, ^11        |
| 7      | 7.x.x             | v11, v12         | 7.4, 8.0, 8.1, 8.2, 8.3, (8.4)    | ^9, ^10         |
| 6      | 6.x.x             | v10, v11         | 7.2, 7.3, 7.4, 8.0, 8.1, 8.2, 8.3 | ^8, ^9          |

Testing framework <= 7.x is no longer maintained.

## Documentation

Usage examples within core and for extensions can be found in
[TYPO3 Explained](https://docs.typo3.org/permalink/t3coreapi:testing).
