<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Cookie\ResponseCookie;

interface CookieJar
{
    /**
     * Retrieve all cookies matching the specified constraints.
     *
     * @param Request $request
     *
     * @return array Returns an array (possibly empty) of all cookie matches.
     */
    public function get(Request $request): array;

    /**
     * Retrieve all stored cookies.
     *
     * @return array Returns array in the format `$array[$domain][$path][$cookieName]`.
     */
    public function getAll(): array;

    /**
     * Store a cookie.
     *
     * @param ResponseCookie $cookie
     *
     * @return void
     *
     * @throws HttpException
     */
    public function store(ResponseCookie $cookie): void;

    /**
     * Remove a specific cookie from the storage.
     *
     * @param ResponseCookie $cookie
     *
     * @throws HttpException
     */
    public function remove(ResponseCookie $cookie): void;

    /**
     * Remove all stored cookies.
     */
    public function removeAll(): void;
}
