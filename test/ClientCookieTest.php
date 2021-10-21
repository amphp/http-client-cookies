<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Cookie\CookieAttributes;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Http\Server\Options;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response as ServerResponse;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\Socket;
use Amp\Socket\StaticConnector;
use Psr\Log\NullLogger;
use function Amp\Promise\wait;
use function Amp\Socket\connector;

class ClientCookieTest extends CookieTest
{
    /** @var HttpClient */
    private $client;

    /** @var InMemoryCookieJar */
    private $jar;

    /** @var Server */
    private $server;

    /** @var string */
    private $address;

    /** @var string */
    private $cookieHeader;

    public function provideCookieDomainMatchData(): array
    {
        return [
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.foo.bar.example.com')),
                'foo.bar',
                false,
            ],
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
                true,
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
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('com')),
                'anotherexample.com',
                false,
            ],
            [
                new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('.com')),
                'anotherexample.com',
                false,
            ],
            [new ResponseCookie('foo', 'bar', CookieAttributes::empty()->withDomain('')), 'example.com', true],
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->jar = new InMemoryCookieJar;

        $socket = Socket\Server::listen('127.0.0.1:0');
        $socket->unreference();

        $this->address = $socket->getAddress();
        $this->server = new Server([$socket], new CallableRequestHandler(function () {
            return new ServerResponse(Status::OK, ['set-cookie' => $this->cookieHeader], '');
        }), new NullLogger, (new Options)->withHttp1Timeout(1)->withHttp2Timeout(1));

        wait($this->server->start());

        $this->client = (new HttpClientBuilder)
            ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory(new StaticConnector(
                $this->address,
                connector()
            ))))
            ->interceptNetwork(new CookieInterceptor($this->jar))
            ->build();
    }

    /**
     * @dataProvider provideCookieDomainMatchData
     *
     * @param ResponseCookie $cookie
     * @param string         $requestDomain
     * @param bool           $accept
     *
     * @return \Generator
     */
    public function testCookieAccepting(ResponseCookie $cookie, string $requestDomain, bool $accept): \Generator
    {
        $this->cookieHeader = (string) $cookie;

        /** @var Response $response */
        $response = yield $this->client->request(new Request('http://' . $requestDomain . '/'));
        yield $response->getBody()->buffer();

        $cookies = $this->jar->getAll();

        if ($accept) {
            $this->assertCount(1, $cookies);
        } else {
            $this->assertSame([], $cookies);
        }

        wait($this->server->stop());
    }
}
