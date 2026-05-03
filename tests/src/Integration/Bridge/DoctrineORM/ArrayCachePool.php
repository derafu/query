<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge\DoctrineORM;

use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Minimal in-memory PSR-6 cache pool for tests.
 *
 * Avoids the symfony/cache requirement when bootstrapping Doctrine ORM
 * in test environments.
 */
final class ArrayCachePool implements CacheItemPoolInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function getItem(string $key): CacheItemInterface
    {
        $hit = array_key_exists($key, $this->data);
        return new ArrayCacheItem($key, $hit ? $this->data[$key] : null, $hit);
    }

    /** @return iterable<string, CacheItemInterface> */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function clear(): bool
    {
        $this->data = [];
        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->data[$key]);
        }
        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->data[$item->getKey()] = $item->get();
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

/**
 * Minimal PSR-6 cache item for ArrayCachePool.
 */
final class ArrayCacheItem implements CacheItemInterface
{
    private mixed $value;

    public function __construct(
        private readonly string $key,
        mixed $value,
        private bool $hit,
    ) {
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;
        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        return $this;
    }
}
