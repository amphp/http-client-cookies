<h1 align="center"><img src="https://raw.githubusercontent.com/amphp/logo/master/repos/http-client.png?v=05-11-2019" alt="HTTP Client" width="350"></h1>

[![Build Status](https://img.shields.io/travis/amphp/http-client-cookies/master.svg?style=flat-square)](https://travis-ci.org/amphp/http-client-cookies)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/http-client-cookies/master.svg?style=flat-square)](https://coveralls.io/github/amphp/http-client-cookies?branch=master)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

This package provides automatic cookie handling as a plugin for [`amphp/http-client`](https://github.com/amphp/http-client).

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-client-cookies
```

## Usage

`Amp\Http\Client\Cookie\CookieInterceptor` must be registered as a `NetworkInterceptor` to enable automatic cookie handling.
It requires a `CookieJar` implementation, where you can choose between `InMemoryCookieJar`, `FileCookieJar` and `NullCookieJar`.

```php
<?php

use Amp\Http\Client\Cookie\CookieInterceptor;
use Amp\Http\Client\Cookie\InMemoryCookieJar;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;

require __DIR__ . '/vendor/autoload.php';

Loop::run(static function () {
    $cookieJar = new InMemoryCookieJar;

    $httpClient = (new HttpClientBuilder)
        ->interceptNetwork(new CookieInterceptor($cookieJar))
        ->build();

    /** @var Response $firstResponse */
    $firstResponse = yield $httpClient->request(new Request('https://google.com/'));
    yield $firstResponse->getBody()->buffer();

    /** @var Response $secondResponse */
    $secondResponse = yield $httpClient->request(new Request('https://google.com/'));
    yield $secondResponse->getBody()->buffer();

    /** @var Response $otherDomainResponse */
    $otherDomainResponse = yield $httpClient->request(new Request('https://amphp.org/'));
    yield $otherDomainResponse->getBody()->buffer();

    print "== first response stores cookies ==\r\n";
    print \implode("\r\n", $firstResponse->getHeaderArray('set-cookie'));
    print "\r\n\r\n";

    print "== second request sends cookies again ==\r\n";
    print \implode("\r\n", $secondResponse->getRequest()->getHeaderArray('cookie'));
    print "\r\n\r\n";

    print "== other domain request does not send cookies ==\r\n";
    print \implode("\r\n", $otherDomainResponse->getRequest()->getHeaderArray('cookie'));
});
```

## Examples

More extensive code examples reside in the [`examples`](./examples) directory.

## Versioning

`amphp/http-client-cookies` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
