<?php

namespace Amp\Http\Client\Cookie;

use Amp\CancellationToken;
use Amp\Dns\InvalidNameException;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Cookie\Internal\PublicSuffixList;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Cookie\RequestCookie;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Promise;
use function Amp\call;

final class CookieInterceptor implements NetworkInterceptor
{
    /** @var CookieJar */
    private $cookieJar;

    public function __construct(CookieJar $cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    public function requestViaNetwork(Request $request, CancellationToken $cancellation, Stream $stream): Promise
    {
        return call(function () use ($request, $cancellation, $stream) {
            yield from $this->assignApplicableRequestCookies($request);

            $request->interceptPush(function (Response $response) {
                yield from $this->storeCookies($response);
            });

            /** @var Response $response */
            $response = yield $stream->request($request, $cancellation);

            yield from $this->storeCookies($response);

            return $response;
        });
    }

    private function assignApplicableRequestCookies(Request $request): \Generator
    {
        $applicableCookies = yield $this->cookieJar->get($request->getUri());

        if (!$applicableCookies) {
            return; // No cookies matched our request; we're finished.
        }

        $cookiePairs = [];

        /** @var RequestCookie $cookie */
        foreach ($applicableCookies as $cookie) {
            $cookiePairs[] = (string) $cookie;
        }

        if ($cookiePairs) {
            if ($request->hasHeader('cookie')) {
                \array_unshift($cookiePairs, $request->getHeader('cookie'));
            }

            $request->setHeader('cookie', \implode('; ', $cookiePairs));
        }
    }

    private function createResponseCookie(string $requestDomain, string $rawCookieStr): ?ResponseCookie
    {
        try {
            $cookie = ResponseCookie::fromHeader($rawCookieStr);
            if ($cookie === null) {
                return null;
            }

            if (!$cookie->getDomain()) {
                $cookie = $cookie->withDomain($requestDomain);
            } else {
                // https://tools.ietf.org/html/rfc6265#section-4.1.2.3
                $cookieDomain = $cookie->getDomain();

                // If a domain is set, left dots are ignored and it's always a wildcard
                $cookieDomain = \ltrim($cookieDomain, '.');

                if ($cookieDomain !== $requestDomain) {
                    // ignore cookies on domains that are public suffixes
                    if (PublicSuffixList::isPublicSuffix($cookieDomain)) {
                        return null;
                    }

                    // cookie origin would not be included when sending the cookie
                    $cookieDomainLength = \strlen($cookieDomain);
                    if (\substr($requestDomain, 0, -$cookieDomainLength - 1) . '.' . $cookieDomain !== $requestDomain) {
                        return null;
                    }
                }

                // always add the dot, it's used internally for wildcard matching when an explicit domain is sent
                $cookie = $cookie->withDomain('.' . $cookieDomain);
            }

            return $cookie;
        } catch (InvalidNameException $e) {
            // Ignore malformed Set-Cookie headers
        }

        return null;
    }

    /**
     * @param Response $response
     *
     * @return \Generator
     * @throws \Amp\Http\Client\HttpException
     */
    private function storeCookies(Response $response): \Generator
    {
        if ($response->hasHeader('set-cookie')) {
            $requestDomain = $response->getRequest()->getUri()->getHost();
            $rawCookies = $response->getHeaderArray('set-cookie');
            $cookies = [];

            foreach ($rawCookies as $rawCookie) {
                $cookie = $this->createResponseCookie($requestDomain, $rawCookie);
                if ($cookie !== null) {
                    $cookies[] = $cookie;
                }
            }

            if ($cookies) {
                yield $this->cookieJar->store(...$cookies);
            }
        }
    }
}
