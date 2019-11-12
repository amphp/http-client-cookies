<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Cookie\ResponseCookie;
use Amp\Promise;
use Amp\Success;
use Psr\Http\Message\UriInterface as PsrUri;

final class NullCookieJar implements CookieJar
{
    public function get(PsrUri $uri): Promise
    {
        return new Success([]);
    }

    public function store(ResponseCookie ...$cookie): Promise
    {
        return new Success; // nothing to do
    }
}
