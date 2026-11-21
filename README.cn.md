[English](README.md) | [Русский](README.ru.md) | **中文**

> 本文由模型翻译，尚未经人工审校；如有出入，以英文版为准。

# mercurius-search

面向 [AMPHP](https://amphp.org) 智能体的网络搜索，统一在一个接口之后。属于
[Mercurius](https://github.com/opencck/mercurius)，也可单独使用。

- `SearchProvider` —— `search(query, limit)` 返回 `SearchResult`：页面（标题、url、摘要、位置），以及在提供方给出时的一段简短回答。`toText()` 将其渲染成适合模型阅读的文本。
- 提供方：`SearxngProvider`（免费，自托管）、`BraveProvider`（Brave Search API）、`TavilyProvider`、`OpenRouterProvider`（带 OpenRouter 网络插件的模型）。
- 调用模型的搜索会告知该次调用的花费：`SearchResult::$usage`（`SearchUsage`：模型、令牌数，提供方给出时还有金额）。来自缓存的回答没有该信息：调用只计一次。
- `FallbackSearch` 按顺序尝试各提供方；`CachedSearch` 将回答保留一段时间（默认 10 分钟）。
- 错误为 `SearchException`，消息中不含密钥。

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

其设计参照了
[hermes-agent](https://github.com/NousResearch/hermes-agent) 的搜索提供方（`agent/web_search_provider.py`、`plugins/web/*`）。

## 检查

`composer check` —— PHPUnit、PHPStan（max）和 Psalm（level 1）。

## 许可证

MIT
