<?php

declare(strict_types=1);

namespace Mercurius\Search;

/**
 * What a search found: the pages and, when the provider writes one, a short answer built on them.
 */
final class SearchResult {
    /** @param list<SearchHit> $hits */
    public function __construct(
        public readonly string $provider,
        public readonly array $hits,
        public readonly ?string $answer = null,
        /** the model call the search made; null — none, or the answer came from a cache */
        public readonly ?SearchUsage $usage = null,
    ) {}

    /** The same result without its cost: what a cache gives back. */
    public function withoutUsage(): self {
        return new self($this->provider, $this->hits, $this->answer);
    }

    /** Plain text for a model: the answer, then the pages with links. */
    public function toText(): string {
        $parts = [];
        if ($this->answer !== null && trim($this->answer) !== '') {
            $parts[] = trim($this->answer);
        }
        if ($this->hits !== []) {
            $parts[] = "Источники:\n" . implode("\n", array_map(
                static fn(SearchHit $h): string => sprintf('%d. %s%s%s', $h->position, $h->title === '' ? '' : $h->title . ' — ', $h->url, $h->snippet === '' ? '' : "\n   " . $h->snippet),
                $this->hits,
            ));
        }
        return $parts === [] ? 'Ничего не найдено.' : implode("\n\n", $parts);
    }
}
