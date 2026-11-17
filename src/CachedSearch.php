<?php

declare(strict_types=1);

namespace Mercurius\Search;

use Amp\Cancellation;
use Closure;

/**
 * Keeps answers for a while: the same query does not pay twice. Failures are not kept.
 *
 * ref: references/hermes-agent/tools/web_result_cache.py@e96221fa:L80 — a short-lived cache of results
 */
final class CachedSearch implements SearchProvider {
    /** @var array<string, array{float, SearchResult}> */
    private array $entries = [];

    /** @var Closure(): float */
    private readonly Closure $clock;

    /** @param (Closure(): float)|null $clock */
    public function __construct(
        private readonly SearchProvider $inner,
        private readonly float $ttl = 600.0,
        private readonly int $size = 200,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): float => microtime(true);
    }

    public function name(): string {
        return $this->inner->name();
    }

    public function search(string $query, int $limit = 5, ?Cancellation $cancellation = null): SearchResult {
        $key = $limit . "\0" . mb_strtolower(trim(preg_replace('~\s+~u', ' ', $query) ?? $query));
        $now = ($this->clock)();
        if (isset($this->entries[$key]) && $this->entries[$key][0] > $now) {
            return $this->entries[$key][1];
        }
        $result = $this->inner->search($query, $limit, $cancellation);
        unset($this->entries[$key]);
        $this->entries[$key] = [$now + $this->ttl, $result];
        while (count($this->entries) > $this->size && ($oldest = array_key_first($this->entries)) !== null) {
            unset($this->entries[$oldest]);
        }
        return $result;
    }
}
