<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Cookie\ResponseCookie;
use Psr\Http\Message\UriInterface as PsrUri;

final class NullCookieJar implements CookieJar
{
    public function get(PsrUri $uri): array
    {
        return [];
    }

    public function store(ResponseCookie ...$cookie): void
    {
        // nothing to do
    }
}
