<?php

declare(strict_types=1);

namespace Mercurius\Search\Provider;

use Amp\Cancellation;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Mercurius\Search\Internal\Json;
use Mercurius\Search\SearchProvider;
use Mercurius\Search\SearchResult;
use SensitiveParameter;

/**
 * Brave Search API (a free tier exists).
 *
 * ref: references/hermes-agent/plugins/web/brave_free/provider.py@e96221fa:L17-L45 — the endpoint, the
 *      X-Subscription-Token header, web.results with title, url, description
 */
final class BraveProvider implements SearchProvider {
    private const URL = 'https://api.search.brave.com/res/v1/web/search';

    private readonly string $key;

    public function __construct(
        private readonly HttpClient $http,
        #[SensitiveParameter] string $key,
    ) {
        $this->key = $key;
    }

    public function name(): string {
        return 'brave';
    }

    public function search(string $query, int $limit = 5, ?Cancellation $cancellation = null): SearchResult {
        $request = new Request(self::URL . '?' . http_build_query(['q' => $query, 'count' => min(20, $limit)]));
        $request->setHeader('x-subscription-token', $this->key);
        return new SearchResult($this->name(), Json::hits(Json::rows(Json::request($this->http, $request, 'Brave', $cancellation), 'web', 'results'), 'description', $limit));
    }
}
