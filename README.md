# Shed Command Line tool

[![CircleCI branch](https://img.shields.io/circleci/project/github/shedcollective/shed-cli-tool.svg)](https://circleci.com/gh/shedcollective/shed-cli-tool)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/shedcollective/shed-cli-tool/badges/quality-score.png)](https://scrutinizer-ci.com/g/shedcollective/shed-cli-tool)

The Shed command line tool makes dev life at Shed a breeze.

## Installation:

### Using Homebrew
```bash
brew tap shedcollective/utilities
brew install shed
```

### Using Composer
```bash
composer global require shedcollective/command-line-tool
```

### Manually

1. Clone this repository
2. Add `dist` to your `$PATH`


## Usage

Execute `shed` with no parameters to explore the available commands.


## Development

This project uses [humbug/box](https://github.com/humbug/box) for compilation. You may use the following commands for development:

```bash
composer build
composer test
```
