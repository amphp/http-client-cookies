<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Cookie;

class InMemoryCookieJarTest extends CookieJarTest
{
    protected function createJar(): CookieJar
    {
        return new InMemoryCookieJar;
    }
}
