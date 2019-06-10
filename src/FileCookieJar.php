<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Cookie\ResponseCookie;

class FileCookieJar extends ArrayCookieJar
{
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

                $this->store($cookie);
            }
        }

        $this->storagePath = $storagePath;
    }

    public function __destruct()
    {
        $cookieData = '';

        foreach ($this->getAll() as $pathArr) {
            foreach ($pathArr as $cookieArr) {
                foreach ($cookieArr as $cookie) {
                    /** @var $cookie ResponseCookie */
                    if ($cookie->getExpiry() && $cookie->getExpiry()->getTimestamp() < \time()) {
                        $cookieData .= $cookie . PHP_EOL;
                    }
                }
            }
        }

        \file_put_contents($this->storagePath, $cookieData);
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
        if (!@\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            throw new \RuntimeException(
                'Failed creating cookie storage directory: ' . $dir
            );
        }
    }
}
