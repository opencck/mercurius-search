<?php

declare(strict_types=1);

namespace Mercurius\Search;

/**
 * One result of a search: a page with its title and a snippet, numbered from 1 in the order of the provider.
 */
final class SearchHit {
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $snippet,
        public readonly int $position,
    ) {}
}
