[English](README.md) | **Русский** | [中文](README.cn.md)

# mercurius-search

Поиск в интернете для агентов на [AMPHP](https://amphp.org) за одним интерфейсом. Часть
[Меркурия](https://github.com/opencck/mercurius), годится и сам по себе.

- `SearchProvider` — `search(запрос, лимит)` возвращает `SearchResult`: страницы (заголовок, адрес, фрагмент, номер)
  и, если поставщик его пишет, краткий ответ. `toText()` — текст для модели.
- Поставщики: `SearxngProvider` (бесплатный, свой сервер), `BraveProvider` (Brave Search API), `TavilyProvider`,
  `OpenRouterProvider` (модель с веб-плагином OpenRouter).
- Поиск, который вызывает модель, сообщает расход вызова: `SearchResult::$usage` (`SearchUsage`: модель, токены, деньги,
  если поставщик их называет). У ответа из кеша расхода нет: вызов уже посчитан один раз.
- `FallbackSearch` пробует поставщиков по порядку; `CachedSearch` помнит ответы какое-то время (по умолчанию 10 минут).
- Ошибки — `SearchException`, ключей в сообщении нет.

```php
use Amp\Http\Client\HttpClientBuilder;
use Mercurius\Search\{CachedSearch, FallbackSearch};
use Mercurius\Search\Provider\{BraveProvider, SearxngProvider};

$http = HttpClientBuilder::buildDefault();
$search = new CachedSearch(new FallbackSearch([
    new SearxngProvider($http, 'http://searxng:8080'),
    new BraveProvider($http, getenv('BRAVE_SEARCH_API_KEY')),
]));
echo $search->search('дата выхода PHP 8.6', 5)->toText();
```

Устройство — по образцу поставщиков поиска
[hermes-agent](https://github.com/NousResearch/hermes-agent) (`agent/web_search_provider.py`, `plugins/web/*`).

## Проверки

`composer check` — PHPUnit, PHPStan (max) и Psalm (уровень 1).

## Лицензия

MIT
