<?php

declare(strict_types=1);

namespace Mercurius\Search\Provider;

use Amp\Cancellation;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Mercurius\Search\Internal\Json;
use Mercurius\Search\SearchHit;
use Mercurius\Search\SearchProvider;
use Mercurius\Search\SearchResult;
use Mercurius\Search\SearchUsage;
use SensitiveParameter;

/**
 * Search through a model on OpenRouter with its web plugin: an answer and the pages it cites (url_citation). Costs a
 * model call on top of the search.
 */
final class OpenRouterProvider implements SearchProvider {
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';

    private readonly string $key;

    public function __construct(
        private readonly HttpClient $http,
        #[SensitiveParameter] string $key,
        /** an OpenRouter model id; the web plugin is asked for explicitly */
        private readonly string $model,
    ) {
        $this->key = $key;
    }

    public function name(): string {
        return 'openrouter';
    }

    /** @param array<array-key, mixed> $data */
    private function usage(array $data): ?SearchUsage {
        $usage = isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : null;
        if ($usage === null) {
            return null;
        }
        $int = static fn(mixed $v): int => is_int($v) ? max(0, $v) : 0;
        /** @var mixed $cost */
        $cost = $usage['cost'] ?? null;
        return new SearchUsage($this->model, $int($usage['prompt_tokens'] ?? 0), $int($usage['completion_tokens'] ?? 0), is_int($cost) || is_float($cost) ? (float) $cost : null);
    }

    public function search(string $query, int $limit = 5, ?Cancellation $cancellation = null): SearchResult {
        $request = new Request(self::URL, 'POST', json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Ты помощник поиска. Ответь на запрос кратко и по существу по найденным страницам, со ссылками на источники. Если ответа нет — так и скажи.'],
                ['role' => 'user', 'content' => $query],
            ],
            'plugins' => [['id' => 'web', 'max_results' => min(10, $limit)]],
            'max_tokens' => 1500,
            // the cost of the call comes in usage: the caller counts it to whoever searched
            'usage' => ['include' => true],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $request->setHeader('authorization', 'Bearer ' . $this->key);
        $request->setHeader('content-type', 'application/json');
        $data = Json::request($this->http, $request, 'OpenRouter', $cancellation, 80.0);
        $choices = Json::rows($data, 'choices');
        $message = isset($choices[0]['message']) && is_array($choices[0]['message']) ? $choices[0]['message'] : [];
        $hits = [];
        foreach (Json::rows($message, 'annotations') as $annotation) {
            $citation = isset($annotation['url_citation']) && is_array($annotation['url_citation']) ? $annotation['url_citation'] : [];
            $url = Json::str($citation, 'url');
            if ($url !== '' && !isset($hits[$url]) && count($hits) < $limit) {
                $hits[$url] = new SearchHit(Json::str($citation, 'title'), $url, '', count($hits) + 1);
            }
        }
        $answer = Json::str($message, 'content');
        return new SearchResult($this->name(), array_values($hits), $answer === '' ? null : $answer, $this->usage($data));
    }
}
