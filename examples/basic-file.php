<?php

use Amp\Http\Client\Cookie\CookieInterceptor;
use Amp\Http\Client\Cookie\FileCookieJar;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () {
    $cookieJar = new FileCookieJar(__DIR__ . '/basic-file.cookies');

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

    print "== first request cookies ==\r\n";
    print \implode("\r\n", $firstResponse->getRequest()->getHeaderArray('cookie'));
    print "\r\n\r\n";

    print "== first response cookies ==\r\n";
    print \implode("\r\n", $firstResponse->getHeaderArray('set-cookie'));
    print "\r\n\r\n";

    print "== second request sends cookies back ==\r\n";
    print \implode("\r\n", $secondResponse->getRequest()->getHeaderArray('cookie'));
    print "\r\n\r\n";

    print "== other domain request (might send different cookies) ==\r\n";
    print \implode("\r\n", $otherDomainResponse->getRequest()->getHeaderArray('cookie'));
});
