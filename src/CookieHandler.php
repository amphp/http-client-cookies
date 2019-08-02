<?php

namespace Amp\Http\Client\Cookie;

use Amp\CancellationToken;
use Amp\Dns\InvalidNameException;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Cookie\Internal\PublicSuffixList;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Promise;
use function Amp\call;

final class CookieHandler implements NetworkInterceptor
{
    /** @var CookieJar */
    private $cookieJar;

    public function __construct(CookieJar $cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    public function interceptNetworkRequest(
        Request $request,
        CancellationToken $cancellationToken,
        Connection $connection
    ): Promise {
        return call(function () use ($request, $cancellationToken, $connection) {
            $request = $this->assignApplicableRequestCookies($request);

            /** @var Response $response */
            $response = yield $connection->request($request, $cancellationToken);

            if ($response->hasHeader('Set-Cookie')) {
                $requestDomain = $response->getRequest()->getUri()->getHost();
                $cookies = $response->getHeaderArray('Set-Cookie');

                foreach ($cookies as $rawCookieStr) {
                    $this->storeResponseCookie($requestDomain, $rawCookieStr);
                }
            }

            return $response;
        });
    }

    private function assignApplicableRequestCookies(Request $request): Request
    {
        if (!$applicableCookies = $this->cookieJar->get($request)) {
            // No cookies matched our request; we're finished.
            return $request;
        }

        $isRequestSecure = \strcasecmp($request->getUri()->getScheme(), 'https') === 0;
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

            return $request->withHeader('cookie', \implode('; ', $cookiePairs));
        }

        return $request;
    }

    /**
     * @param string $requestDomain
     * @param string $rawCookieStr
     *
     * @throws HttpException
     */
    private function storeResponseCookie(string $requestDomain, string $rawCookieStr): void
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
                    if (\substr($requestDomain, 0, -\strlen($cookieDomain) - 1) . '.' . $cookieDomain !== $requestDomain) {
                        return;
                    }
                }

                // always add the dot, it's used internally for wildcard matching when an explicit domain is sent
                $cookie = $cookie->withDomain('.' . $cookieDomain);
            }

            $this->cookieJar->store($cookie);
        } catch (InvalidNameException $e) {
            // Ignore malformed Set-Cookie headers
        }
    }
}
