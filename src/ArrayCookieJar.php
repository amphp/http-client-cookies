<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Cookie\ResponseCookie;

class ArrayCookieJar implements CookieJar
{
    private $cookies = [];

    /** @inheritDoc */
    public function store(ResponseCookie $cookie): void
    {
        if ($cookie->getDomain() === '') {
            throw new HttpException("Can't store cookie without domain information.");
        }

        $this->cookies[$cookie->getDomain()][$cookie->getPath() ?: '/'][$cookie->getName()] = $cookie;
    }

    /** @inheritDoc */
    public function remove(ResponseCookie $cookie): void
    {
        if ($cookie->getDomain() === '') {
            throw new HttpException("Can't clear cookie without domain information.");
        }

        unset($this->cookies[$cookie->getDomain()][$cookie->getPath() ?: '/'][$cookie->getName()]);
    }

    /** @inheritDoc */
    public function removeAll(): void
    {
        $this->cookies = [];
    }

    /** @inheritDoc */
    public function getAll(): array
    {
        return $this->cookies;
    }

    /** @inheritDoc */
    public function get(Request $request): array
    {
        $this->clearExpiredCookies();

        $path = $request->getUri()->getPath() ?: '/';
        $domain = \strtolower($request->getUri()->getHost());

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
                    $matches[] = $cookie;
                }
            }
        }

        return $matches;
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
            $isMatch = true;
        } elseif (\strpos($requestPath, $cookiePath) === 0
            && (\substr($cookiePath, -1) === '/' || $requestPath[\strlen($cookiePath)] === '/')
        ) {
            $isMatch = true;
        } else {
            $isMatch = false;
        }

        return $isMatch;
    }
}
