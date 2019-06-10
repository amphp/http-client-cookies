<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\Request;
use Amp\Http\Cookie\ResponseCookie;

class NullCookieJar implements CookieJar
{
    public function get(Request $request): array
    {
        return [];
    }

    public function getAll(): array
    {
        return [];
    }

    public function store(ResponseCookie $cookie): void
    {
        // nothing to do
    }

    public function remove(ResponseCookie $cookie): void
    {
        // nothing to do
    }

    public function removeAll(): void
    {
        // nothing to do
    }
}
