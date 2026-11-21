**English** | [Русский](README.ru.md) | [中文](README.cn.md)

# mercurius-search

Web search for [AMPHP](https://amphp.org) agents behind one interface. Part of
[Mercurius](https://github.com/opencck/mercurius), usable on its own.

- `SearchProvider` — `search(query, limit)` returns a `SearchResult`: pages (title, url, snippet, position) and,
  when the provider writes one, a short answer. `toText()` renders it for a model.
- Providers: `SearxngProvider` (free, self-hosted), `BraveProvider` (Brave Search API), `TavilyProvider`,
  `OpenRouterProvider` (a model with the OpenRouter web plugin).
- A search that calls a model tells what the call cost: `SearchResult::$usage` (`SearchUsage`: model, tokens, money
  when the provider says). An answer from the cache has none: the call was counted once.
- `FallbackSearch` tries providers in order; `CachedSearch` keeps answers for a while (10 minutes by default).
- Errors are `SearchException` with no keys in the message.

```php
use Amp\Http\Client\HttpClientBuilder;
use Mercurius\Search\{CachedSearch, FallbackSearch};
use Mercurius\Search\Provider\{BraveProvider, SearxngProvider};

$http = HttpClientBuilder::buildDefault();
$search = new CachedSearch(new FallbackSearch([
    new SearxngProvider($http, 'http://searxng:8080'),
    new BraveProvider($http, getenv('BRAVE_SEARCH_API_KEY')),
]));
echo $search->search('PHP 8.6 release date', 5)->toText();
```

The design follows the search providers of
[hermes-agent](https://github.com/NousResearch/hermes-agent) (`agent/web_search_provider.py`, `plugins/web/*`).

## Checks

`composer check` — PHPUnit, PHPStan (max) and Psalm (level 1).

## License

MIT
