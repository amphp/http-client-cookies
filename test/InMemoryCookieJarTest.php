<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Test\Artax\Cookie;

use Amp\Http\Client\Cookie\CookieJar;
use Amp\Http\Client\Cookie\CookieTest;
use Amp\Http\Client\Cookie\InMemoryCookieJar;
use Amp\Http\Cookie\CookieAttributes;
use Amp\Http\Cookie\ResponseCookie;

class InMemoryCookieJarTest extends CookieTest
{
    /** @var CookieJar */
    private $jar;

    public function setUp(): void
    {
        parent::setUp();

        $this->jar = new InMemoryCookieJar;
    }

    /**
     * @dataProvider provideCookieDomainMatchData
     *
     * @param ResponseCookie $cookie
     * @param string         $domain
     * @param bool           $returned
     */
    public function testCookieDomainMatching(ResponseCookie $cookie, string $domain, bool $returned): void
    {
        $this->jar->store($cookie);

        $requestCookies = $this->jar->get($this->getUri('https', $domain, '/'));

        if ($returned) {
            $this->assertSame([$cookie], $requestCookies);
        } else {
            $this->assertSame([], $requestCookies);
        }
    }

    public function provideCookieDomainMatchData(): array
    {
        // See http://stackoverflow.com/a/1063760/2373138 for cases
        return [
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.foo.bar.example.com')),
                'foo.bar',
                false,
            ], /* previous security issue */
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.example.com')),
                'example.com',
                true,
            ],
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.example.com')),
                'www.example.com',
                true,
            ],
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('example.com')),
                'example.com',
                true,
            ],
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('example.com')),
                'www.example.com',
                false,
            ],
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('example.com')),
                'anotherexample.com',
                false,
            ],
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('anotherexample.com')),
                'example.com',
                false,
            ],
        ];
    }
}
