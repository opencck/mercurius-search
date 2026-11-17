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
 * Tavily — search made for agents; it can write a short answer itself.
 *
 * ref: references/hermes-agent/plugins/web/tavily/provider.py@e96221fa:L28-L47 — POST /search with a bearer key
 */
final class TavilyProvider implements SearchProvider {
    private readonly string $key;

    public function __construct(
        private readonly HttpClient $http,
        #[SensitiveParameter] string $key,
        private readonly string $baseUrl = 'https://api.tavily.com',
    ) {
        $this->key = $key;
    }

    public function name(): string {
        return 'tavily';
    }

    public function search(string $query, int $limit = 5, ?Cancellation $cancellation = null): SearchResult {
        $request = new Request(rtrim($this->baseUrl, '/') . '/search', 'POST', json_encode([
            'query' => $query,
            'max_results' => min(20, $limit),
            'include_answer' => true,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $request->setHeader('authorization', 'Bearer ' . $this->key);
        $request->setHeader('content-type', 'application/json');
        $data = Json::request($this->http, $request, 'Tavily', $cancellation);
        $answer = Json::str($data, 'answer');
        return new SearchResult($this->name(), Json::hits(Json::rows($data, 'results'), 'content', $limit), $answer === '' ? null : $answer);
    }
}
