<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

/**
 * A single network exchange observed over BiDi `network.*` events: the request's
 * method and URL, and the response status once it completes (null while the
 * request is still in flight or if it never returned).
 */
final readonly class NetworkRecord
{
    public function __construct(
        public string $method,
        public string $url,
        public ?int $status = null,
    ) {}

    public function withStatus(?int $status): self
    {
        return new self($this->method, $this->url, $status ?? $this->status);
    }
}
