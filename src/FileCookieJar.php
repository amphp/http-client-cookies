<?php

namespace Amp\Http\Client\Cookie;

use Amp\File;
use Amp\Http\Client\HttpException;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Promise;
use Amp\Sync\LocalMutex;
use Amp\Sync\Lock;
use Amp\Sync\Mutex;
use Psr\Http\Message\UriInterface as PsrUri;
use function Amp\call;

final class FileCookieJar implements CookieJar
{
    /** @var Promise<InMemoryCookieJar> */
    private $cookieJar;

    /** @var string */
    private $storagePath;

    /** @var Mutex */
    private $mutex;

    public function __construct(string $storagePath, ?Mutex $mutex = null)
    {
        if (!\interface_exists(File\Driver::class)) {
            throw new \Error(self::class . ' requires amphp/file to be installed. Run composer require amphp/file to install it.');
        }

        $this->storagePath = $storagePath;
        $this->mutex = $mutex ?? new LocalMutex;
    }

    public function get(PsrUri $uri): Promise
    {
        return call(function () use ($uri) {
            /** @var CookieJar $cookieJar */
            $cookieJar = yield $this->read();

            return $cookieJar->get($uri);
        });
    }

    public function store(ResponseCookie ...$cookies): Promise
    {
        return call(function () use ($cookies) {
            /** @var InMemoryCookieJar $cookieJar */
            $cookieJar = yield $this->read();

            yield $cookieJar->store(...$cookies);

            yield $this->write($cookieJar);
        });
    }

    private function read(): Promise
    {
        if ($this->cookieJar) {
            return $this->cookieJar;
        }

        return $this->cookieJar = call(function () {
            /** @var Lock $lock */
            $lock = yield $this->mutex->acquire();

            $cookieJar = new InMemoryCookieJar;

            if (!yield File\exists($this->storagePath)) {
                return $cookieJar;
            }

            $lines = \explode("\n", yield File\get($this->storagePath));
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

            $lock->release();

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

            /** @var Lock $lock */
            $lock = yield $this->mutex->acquire();

            if (!yield File\isdir(\dirname($this->storagePath))) {
                yield File\mkdir(\dirname($this->storagePath), 0755, true);

                if (!yield File\isdir(\dirname($this->storagePath))) {
                    throw new HttpException('Failed to create cookie storage directory: ' . $this->storagePath);
                }
            }

            yield File\put($this->storagePath, $cookieData);

            $lock->release();
        });
    }
}
