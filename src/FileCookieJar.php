<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\HttpException;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Promise;
use Psr\Http\Message\UriInterface as PsrUri;

final class FileCookieJar implements CookieJar
{
    /** @var InMemoryCookieJar */
    private $cookieJar;

    /** @var string */
    private $storagePath;

    public function __construct(string $storagePath)
    {
        if (!\file_exists($storagePath)) {
            $cookieFileHandle = $this->createStorageFile($storagePath);
        } elseif (false === ($cookieFileHandle = @\fopen($storagePath, 'rb+'))) {
            throw new \RuntimeException(
                'Failed opening cookie storage file for reading: ' . $storagePath
            );
        }

        while (!\feof($cookieFileHandle)) {
            if ($line = \fgets($cookieFileHandle)) {
                $cookie = ResponseCookie::fromHeader($line);
                if ($cookie === null) {
                    continue;
                }

                try {
                    $this->store($cookie);
                } catch (HttpException $e) {
                    // ignore invalid cookies in storage
                }
            }
        }

        $this->storagePath = $storagePath;
    }

    public function __destruct()
    {
        $cookieData = '';

        foreach ($this->cookieJar->getAll() as $cookie) {
            /** @var $cookie ResponseCookie */
            if ($cookie->getExpiry() && $cookie->getExpiry()->getTimestamp() < \time()) {
                $cookieData .= $cookie . PHP_EOL;
            }
        }

        \file_put_contents($this->storagePath, $cookieData);
    }

    public function get(PsrUri $uri): Promise
    {
        return $this->cookieJar->get($uri);
    }

    public function store(ResponseCookie $cookie): Promise
    {
        return $this->cookieJar->store($cookie);
    }

    private function createStorageFile($storagePath)
    {
        $dir = \dirname($storagePath);
        if (!\is_dir($dir)) {
            $this->createStorageDirectory($dir);
        }

        if (!$cookieFileHandle = @\fopen($storagePath, 'wb+')) {
            throw new \RuntimeException(
                'Failed reading cookie storage file: ' . $storagePath
            );
        }

        return $cookieFileHandle;
    }

    private function createStorageDirectory($dir): void
    {
        if (!@\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            throw new \RuntimeException(
                'Failed creating cookie storage directory: ' . $dir
            );
        }
    }
}
