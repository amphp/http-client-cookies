<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\HttpException;
use Amp\Http\Cookie\InvalidCookieException;
use Amp\Http\Cookie\RequestCookie;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Promise;
use Amp\Success;
use Psr\Http\Message\UriInterface as PsrUri;

final class InMemoryCookieJar implements CookieJar
{
    /** @var ResponseCookie[][][] */
    private $cookies = [];

    public function store(ResponseCookie ...$cookies): Promise
    {
        foreach ($cookies as $cookie) {
            if ($cookie->getDomain() === '') {
                throw new HttpException("Can't store cookie without domain information.");
            }

            $this->cookies[$cookie->getDomain()][$cookie->getPath() ?: '/'][$cookie->getName()] = $cookie;
        }

        return new Success;
    }

    public function get(PsrUri $uri): Promise
    {
        $this->clearExpiredCookies();

        $path = $uri->getPath() ?: '/';
        $domain = $uri->getHost();

        $isRequestSecure = $uri->getScheme() === 'https';

        $matches = [];

        foreach ($this->cookies as $cookieDomain => $domainCookies) {
            if (!$this->matchesDomain($domain, $cookieDomain)) {
                continue;
            }

            foreach ($domainCookies as $cookiePath => $pathCookies) {
                if (!$this->matchesPath($path, $cookiePath)) {
                    continue;
                }

                foreach ($pathCookies as $cookieName => $cookie) {
                    if ($isRequestSecure || !$cookie->isSecure()) {
                        try {
                            $matches[] = new RequestCookie($cookie->getName(), $cookie->getValue());
                        } catch (InvalidCookieException $e) {
                            // ignore cookie
                        }
                    }
                }
            }
        }

        return new Success($matches);
    }

    public function getAll(): array
    {
        $cookies = [];

        foreach ($this->cookies as $cookiesPerDomain) {
            foreach ($cookiesPerDomain as $cookiesPerPath) {
                foreach ($cookiesPerPath as $cookie) {
                    $cookies[] = $cookie;
                }
            }
        }

        return $cookies;
    }

    public function clear(): void
    {
        $this->cookies = [];
    }

    private function clearExpiredCookies(): void
    {
        foreach ($this->cookies as $domain => $domainCookies) {
            foreach ($domainCookies as $path => $pathCookies) {
                foreach ($pathCookies as $name => $cookie) {
                    /** @var ResponseCookie $cookie */
                    if ($cookie->getExpiry() && $cookie->getExpiry()->getTimestamp() < \time()) {
                        unset($this->cookies[$domain][$path][$name]);
                    }
                }
            }
        }
    }

    /**
     * @param string $requestDomain
     * @param string $cookieDomain
     *
     * @return bool
     *
     * @link http://tools.ietf.org/html/rfc6265#section-5.1.3
     */
    private function matchesDomain(string $requestDomain, string $cookieDomain): bool
    {
        if ($requestDomain === \ltrim($cookieDomain, '.')) {
            return true;
        }

        /** @noinspection SubStrUsedAsStrPosInspection */
        $isWildcardCookieDomain = $cookieDomain[0] === '.';
        if (!$isWildcardCookieDomain) {
            return false;
        }

        if (\filter_var($requestDomain, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (\substr($requestDomain, 0, -\strlen($cookieDomain)) . $cookieDomain === $requestDomain) {
            return true;
        }

        return false;
    }

    /**
     * @link http://tools.ietf.org/html/rfc6265#section-5.1.4
     *
     * @param string $requestPath
     * @param string $cookiePath
     *
     * @return bool
     */
    private function matchesPath(string $requestPath, string $cookiePath): bool
    {
        if ($requestPath === $cookiePath) {
            return true;
        }

        if (\strpos($requestPath, $cookiePath) !== 0) {
            return false;
        }

        if ((\substr($cookiePath, -1) === '/' || $requestPath[\strlen($cookiePath)] === '/')) {
            return true;
        }

        return false;
    }
}
