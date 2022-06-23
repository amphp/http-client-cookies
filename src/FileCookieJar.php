<?php

namespace Amp\Http\Client\Cookie;

use Amp\File;
use Amp\File\Filesystem;
use Amp\Future;
use Amp\Http\Client\HttpException;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Sync\LocalMutex;
use Amp\Sync\Mutex;
use Psr\Http\Message\UriInterface as PsrUri;
use function Amp\async;

final class FileCookieJar implements CookieJar
{
    /** @var Future<InMemoryCookieJar>|null */
    private ?Future $cookieJar = null;

    private readonly string $storagePath;

    private readonly Mutex $mutex;

    private bool $persistSessionCookies = false;

    private readonly Filesystem $filesystem;

    public function __construct(string $storagePath, ?Mutex $mutex = null, ?Filesystem $filesystem = null)
    {
        if (!\class_exists(Filesystem::class)) {
            throw new \Error(self::class . ' requires amphp/file to be installed. Run composer require amphp/file to install it.');
        }

        $this->storagePath = $storagePath;
        $this->mutex = $mutex ?? new LocalMutex;
        $this->filesystem = $filesystem ?? File\filesystem();
    }

    public function enableSessionCookiePersistence()
    {
        $this->persistSessionCookies = true;
    }

    public function disableSessionCookiePersistence()
    {
        $this->persistSessionCookies = false;
    }

    public function get(PsrUri $uri): array
    {
        $cookieJar = $this->read();
        return $cookieJar->get($uri);
    }

    public function store(ResponseCookie ...$cookies): void
    {
        $cookieJar = $this->read();

        $cookieJar->store(...$cookies);

        $this->write($cookieJar);
    }

    private function read(): InMemoryCookieJar
    {
        $this->cookieJar ??= async(function (): InMemoryCookieJar {
            $lock = $this->mutex->acquire();

            $cookieJar = new InMemoryCookieJar;

            if (!$this->filesystem->exists($this->storagePath)) {
                return $cookieJar;
            }

            $lines = \explode("\n", $this->filesystem->read($this->storagePath));
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

        return $this->cookieJar->await();
    }

    private function write(InMemoryCookieJar $cookieJar): void
    {
        $cookieData = '';

        foreach ($cookieJar->getAll() as $cookie) {
            /** @var $cookie ResponseCookie */
            if ($cookie->getExpiry() ? $cookie->getExpiry()->getTimestamp() > \time() : $this->persistSessionCookies) {
                $cookieData .= $cookie . "\r\n";
            }
        }

        $lock = $this->mutex->acquire();

        if (!$this->filesystem->isDirectory(\dirname($this->storagePath))) {
            $this->filesystem->createDirectoryRecursively(\dirname($this->storagePath), 0755);

            if (!$this->filesystem->isDirectory(\dirname($this->storagePath))) {
                throw new HttpException('Failed to create cookie storage directory: ' . $this->storagePath);
            }
        }

        $this->filesystem->write($this->storagePath, $cookieData);

        $lock->release();
    }
}
