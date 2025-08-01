<?php

declare(strict_types=1);

namespace Tempest\Http\Cookie;

use Tempest\Clock\Clock;
use Tempest\Core\AppConfig;
use Tempest\DateTime\DateTimeInterface;
use Tempest\Support\Str;

/**
 * Manages cookies that will be sent to the client.
 */
final class CookieManager
{
    /** @var \Tempest\Http\Cookie\Cookie[] */
    private array $cookies = [];

    public function __construct(
        private AppConfig $appConfig,
        private Clock $clock,
    ) {}

    /** @return  \Tempest\Http\Cookie\Cookie[] */
    public function all(): array
    {
        return $this->cookies;
    }

    public function get(string $key): ?Cookie
    {
        return $this->cookies[$key] ?? null;
    }

    /**
     * Adds or updates a cookie that will be sent to the client in this request.
     */
    public function set(string $key, string $value, DateTimeInterface|int|null $expiresAt = null): Cookie
    {
        $cookie = $this->get($key) ?? new Cookie(
            key: $key,
            path: '/',
            secure: Str\starts_with($this->appConfig->baseUri, 'https'),
            httpOnly: true,
            sameSite: SameSite::LAX,
        );

        $cookie->value = $value;
        $cookie->expiresAt = $expiresAt ?? $cookie->expiresAt;

        $this->add($cookie);

        return $cookie;
    }

    /**
     * Adds a cookie that will be sent to the client in this request.
     */
    public function add(Cookie $cookie): void
    {
        if ($cookie->expiresAt !== null) {
            $maxAge = $cookie->getExpiresAtTime() - $this->clock->seconds();

            $cookie->maxAge = max($maxAge, 0);
        }

        $this->cookies[$cookie->key] = $cookie;
    }

    public function remove(string $key): void
    {
        $cookie = new Cookie($key, '', -1);

        $this->add($cookie);
    }
}
