<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Test\Artax\Cookie;

use Amp\Http\Client\Cookie\ArrayCookieJar;
use Amp\Http\Client\Cookie\CookieJar;
use Amp\Http\Client\Request;
use Amp\Http\Cookie\CookieAttributes;
use Amp\Http\Cookie\ResponseCookie;
use PHPUnit\Framework\TestCase;

class ArrayCookieJarTest extends TestCase
{
    /** @var CookieJar */
    private $jar;

    public function setUp(): void
    {
        $this->jar = new ArrayCookieJar;
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

        if ($returned) {
            $this->assertSame([$cookie], $this->jar->get(new Request('https://' . $domain . '/')));
        } else {
            $this->assertSame([], $this->jar->get(new Request('https://' . $domain . '/')));
        }
    }

    public function provideCookieDomainMatchData(): array
    {
        // See http://stackoverflow.com/a/1063760/2373138 for cases
        return [
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.foo.bar.example.com')), 'foo.bar', false], /* previous security issue */
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.example.com')), 'example.com', true],
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.example.com')), 'www.example.com', true],
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('example.com')), 'example.com', true],
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('example.com')), 'www.example.com', false],
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('example.com')), 'anotherexample.com', false],
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('anotherexample.com')), 'example.com', false],
        ];
    }
}
