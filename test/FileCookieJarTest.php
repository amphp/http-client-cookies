<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Test\Artax\Cookie;

use Amp\Http\Client\Cookie\CookieJar;
use Amp\Http\Client\Cookie\CookieJarTest;
use Amp\Http\Client\Cookie\FileCookieJar;

class FileCookieJarTest extends CookieJarTest
{
    protected function createJar(): CookieJar
    {
        return new FileCookieJar(\tempnam(\sys_get_temp_dir(), 'amphp-http-client-cookies-test-'));
    }
}
