<?php

declare(strict_types=1);

namespace Mercurius\Search\Provider;

use Amp\Cancellation;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Mercurius\Search\Internal\Json;
use Mercurius\Search\SearchProvider;
use Mercurius\Search\SearchResult;

/**
 * SearXNG — free self-hosted metasearch; the instance must allow the json format.
 *
 * ref: references/hermes-agent/plugins/web/searxng/provider.py@e96221fa:L24-L39 — /search?q=&format=json, rows sorted
 *      by score and cut to the limit
 */
final class SearxngProvider implements SearchProvider {
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $baseUrl,
    ) {}

    public function name(): string {
        return 'searxng';
    }

    public function search(string $query, int $limit = 5, ?Cancellation $cancellation = null): SearchResult {
        $url = rtrim($this->baseUrl, '/') . '/search?' . http_build_query(['q' => $query, 'format' => 'json', 'pageno' => 1]);
        $rows = Json::rows(Json::request($this->http, new Request($url), 'SearXNG', $cancellation, 20.0), 'results');
        usort($rows, static fn(array $a, array $b): int => (is_numeric($b['score'] ?? null) ? (float) $b['score'] : 0.0) <=> (is_numeric($a['score'] ?? null) ? (float) $a['score'] : 0.0));
        return new SearchResult($this->name(), Json::hits($rows, 'content', $limit));
    }
}
