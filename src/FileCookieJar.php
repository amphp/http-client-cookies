<?php

namespace Amp\Http\Client\Cookie;

use Amp\File\Driver;
use Amp\Http\Client\HttpException;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Promise;
use Psr\Http\Message\UriInterface as PsrUri;
use function Amp\call;
use function Amp\File\exists;
use function Amp\File\get;
use function Amp\File\isdir;
use function Amp\File\mkdir;
use function Amp\File\put;

final class FileCookieJar implements CookieJar
{
    /** @var Promise<InMemoryCookieJar> */
    private $cookieJar;

    /** @var string */
    private $storagePath;

    public function __construct(string $storagePath)
    {
        if (!\interface_exists(Driver::class)) {
            throw new \Error(self::class . ' requires amphp/file to be installed. Run composer require amphp/file to install it.');
        }

        $this->storagePath = $storagePath;
    }

    public function get(PsrUri $uri): Promise
    {
        return call(function () use ($uri) {
            /** @var CookieJar $cookieJar */
            $cookieJar = yield $this->read();

            return $cookieJar->get($uri);
        });
    }

    public function store(ResponseCookie $cookie): Promise
    {
        return call(function () use ($cookie) {
            /** @var InMemoryCookieJar $cookieJar */
            $cookieJar = yield $this->read();
            yield $cookieJar->store($cookie);
            yield $this->write($cookieJar);
        });
    }

    private function read(): Promise
    {
        if ($this->cookieJar) {
            return $this->cookieJar;
        }

        return $this->cookieJar = call(function () {
            $cookieJar = new InMemoryCookieJar;

            if (!yield exists($this->storagePath)) {
                return $cookieJar;
            }

            $lines = \explode("\n", yield get($this->storagePath));
            foreach ($lines as $line) {
                $line = \trim($line);

                if ($line) {
                    $cookie = ResponseCookie::fromHeader($line);
                    if ($cookie === null) {
                        continue;
                    }

                    try {
                        $cookieJar->store($cookie);
                    } catch (HttpException $e) {
                        // ignore invalid cookies in storage
                    }
                }
            }

            return $cookieJar;
        });
    }

    private function write(InMemoryCookieJar $cookieJar): Promise
    {
        return call(function () use ($cookieJar) {
            $cookieData = '';

            foreach ($cookieJar->getAll() as $cookie) {
                /** @var $cookie ResponseCookie */
                if ($cookie->getExpiry() && $cookie->getExpiry()->getTimestamp() > \time()) {
                    $cookieData .= $cookie . "\r\n";
                }
            }

            if (!yield isdir(\dirname($this->storagePath))) {
                yield mkdir(\dirname($this->storagePath), 0755, true);

                if (!yield isdir(\dirname($this->storagePath))) {
                    throw new HttpException('Failed to create cookie storage directory: ' . $this->storagePath);
                }
            }

            yield put($this->storagePath, $cookieData);
        });
    }
}
