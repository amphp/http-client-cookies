<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Cookie;

class InMemoryCookieJarTest extends CookieJarTest
{
    protected function createJar(): CookieJar
    {
        return new LocalCookieJar;
    }
}
