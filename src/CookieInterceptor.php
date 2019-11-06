<?php

namespace Amp\Http\Client\Cookie;

use Amp\CancellationToken;
use Amp\Coroutine;
use Amp\Dns\InvalidNameException;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Cookie\Internal\PublicSuffixList;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Cookie\ResponseCookie;
use Amp\MultiReasonException;
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

            /** @var Response $response */
            $response = yield $stream->request($request, $cancellation);

            if ($response->hasHeader('set-cookie')) {
                $requestDomain = $response->getRequest()->getUri()->getHost();
                $cookies = $response->getHeaderArray('set-cookie');

                $promises = [];

                foreach ($cookies as $rawCookie) {
                    $promises[] = new Coroutine($this->storeResponseCookie($requestDomain, $rawCookie));
                }

                try {
                    yield $promises;
                } catch (MultiReasonException $e) {
                    throw $e->getReasons()[0];
                }
            }

            return $response;
        });
    }

    private function assignApplicableRequestCookies(Request $request): \Generator
    {
        if (!$applicableCookies = yield $this->cookieJar->get($request->getUri())) {
            // No cookies matched our request; we're finished.
            return;
        }

        $isRequestSecure = $request->getUri()->getScheme() === 'https';
        $cookiePairs = [];

        /** @var ResponseCookie $cookie */
        foreach ($applicableCookies as $cookie) {
            if ($isRequestSecure || !$cookie->isSecure()) {
                $cookiePairs[] = $cookie->getName() . '=' . $cookie->getValue();
            }
        }

        if ($cookiePairs) {
            if ($request->hasHeader('cookie')) {
                \array_unshift($cookiePairs, $request->getHeader('cookie'));
            }

            $request->setHeader('cookie', \implode('; ', $cookiePairs));
        }
    }

    private function storeResponseCookie(string $requestDomain, string $rawCookieStr): \Generator
    {
        try {
            $cookie = ResponseCookie::fromHeader($rawCookieStr);
            if ($cookie === null) {
                return;
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
                        return;
                    }

                    // cookie origin would not be included when sending the cookie
                    $cookieDomainLength = \strlen($cookieDomain);
                    if (\substr($requestDomain, 0, -$cookieDomainLength - 1) . '.' . $cookieDomain !== $requestDomain) {
                        return;
                    }
                }

                // always add the dot, it's used internally for wildcard matching when an explicit domain is sent
                $cookie = $cookie->withDomain('.' . $cookieDomain);
            }

            yield $this->cookieJar->store($cookie);
        } catch (InvalidNameException | HttpException $e) {
            // Ignore malformed Set-Cookie headers
        }
    }
}
