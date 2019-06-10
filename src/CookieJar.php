<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\Request;
use Amp\Http\Cookie\ResponseCookie;

interface CookieJar
{
    public function get(Request $request): array;

    public function getAll(): array;

    public function store(ResponseCookie $cookie): void;

    public function remove(ResponseCookie $cookie): void;

    public function removeAll(): void;
}
