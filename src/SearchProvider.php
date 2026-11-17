<?php

declare(strict_types=1);

namespace Mercurius\Search;

use Amp\Cancellation;

/**
 * A web search backend.
 *
 * ref: references/hermes-agent/agent/web_search_provider.py@e96221fa:L64-L101 (WebSearchProvider) — one search method
 *      and a normalized row of title, url, description, position; extraction of pages lives elsewhere here (web_read)
 */
interface SearchProvider {
    /** A short name for logs and results: searxng, brave, tavily, openrouter. */
    public function name(): string;

    /**
     * @param positive-int $limit
     * @throws SearchException
     */
    public function search(string $query, int $limit = 5, ?Cancellation $cancellation = null): SearchResult;
}
