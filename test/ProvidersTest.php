<?php

declare(strict_types=1);

namespace Mercurius\Search\Test;

use Amp\PHPUnit\AsyncTestCase;
use Mercurius\Search\CachedSearch;
use Mercurius\Search\FallbackSearch;
use Mercurius\Search\Provider\BraveProvider;
use Mercurius\Search\Provider\OpenRouterProvider;
use Mercurius\Search\Provider\SearxngProvider;
use Mercurius\Search\Provider\TavilyProvider;
use Mercurius\Search\SearchException;
use Mercurius\Search\SearchHit;

final class ProvidersTest extends AsyncTestCase {
    private static function json(mixed $data): string {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function testSearxngSortsByScoreAndCutsToTheLimit(): void {
        $fake = new FakeHttp([[200, self::json(['results' => [
            ['title' => 'Low', 'url' => 'https://low.example', 'content' => 'low', 'score' => 0.2],
            ['title' => 'High', 'url' => 'https://high.example', 'content' => 'high', 'score' => 3.1],
            ['title' => 'Bad', 'url' => 'javascript:alert(1)', 'content' => 'x', 'score' => 9],
            ['title' => 'Mid', 'url' => 'https://mid.example', 'content' => 'mid', 'score' => 1],
        ]])]]);

        $result = (new SearxngProvider(FakeHttp::client($fake), 'http://searxng:8080/'))->search('php 8.6', 2);

        self::assertSame(['https://high.example', 'https://mid.example'], array_map(static fn(SearchHit $h): string => $h->url, $result->hits));
        self::assertSame([1, 2], array_map(static fn(SearchHit $h): int => $h->position, $result->hits));
        self::assertSame('http://searxng:8080/search?q=php+8.6&format=json&pageno=1', (string) $fake->requests[0]->getUri());
    }

    public function testBraveSendsTheKeyInItsHeaderAndReadsWebResults(): void {
        $fake = new FakeHttp([[200, self::json(['web' => ['results' => [['title' => 'PHP', 'url' => 'https://php.net', 'description' => 'PHP 8.6 in November']]]])]]);

        $result = (new BraveProvider(FakeHttp::client($fake), 'brave-key'))->search('php', 5);

        self::assertSame('brave-key', $fake->requests[0]->getHeader('x-subscription-token'));
        self::assertSame('PHP 8.6 in November', $result->hits[0]->snippet ?? null);
        self::assertSame("Источники:\n1. PHP — https://php.net\n   PHP 8.6 in November", $result->toText());
    }

    public function testTavilyKeepsItsAnswer(): void {
        $fake = new FakeHttp([[200, self::json(['answer' => 'В ноябре 2026.', 'results' => [['title' => 'php.net', 'url' => 'https://php.net', 'content' => 'release']]])]]);

        $result = (new TavilyProvider(FakeHttp::client($fake), 'tvly-key'))->search('когда php 8.6', 3);

        self::assertSame('В ноябре 2026.', $result->answer);
        self::assertSame('Bearer tvly-key', $fake->requests[0]->getHeader('authorization'));
        self::assertStringStartsWith("В ноябре 2026.\n\nИсточники:", $result->toText());
    }

    public function testOpenRouterTakesTheCitedPages(): void {
        $fake = new FakeHttp([[200, self::json(['choices' => [['message' => [
            'content' => 'Выйдет в ноябре.',
            'annotations' => [
                ['type' => 'url_citation', 'url_citation' => ['url' => 'https://php.net/a', 'title' => 'A']],
                ['type' => 'url_citation', 'url_citation' => ['url' => 'https://php.net/a', 'title' => 'A again']],
                ['type' => 'url_citation', 'url_citation' => ['url' => 'https://wiki.example/b', 'title' => 'B']],
            ],
        ]]]])]]);

        $result = (new OpenRouterProvider(FakeHttp::client($fake), 'sk-or', 'openai/gpt-x:online'))->search('php', 5);

        self::assertSame('Выйдет в ноябре.', $result->answer);
        self::assertSame(['https://php.net/a', 'https://wiki.example/b'], array_map(static fn(SearchHit $h): string => $h->url, $result->hits));
    }

    public function testAFailedProviderHandsOverAndErrorsCarryNoKey(): void {
        $fake = new FakeHttp([[401, '{"error":"bad key brave-secret"}'], [200, self::json(['results' => [['title' => 'ok', 'url' => 'https://ok.example', 'content' => '']]])]]);
        $http = FakeHttp::client($fake);
        $search = new FallbackSearch([new BraveProvider($http, 'brave-secret'), new SearxngProvider($http, 'http://searxng:8080')]);

        self::assertSame('searxng', $search->search('x')->provider);
        self::assertSame('brave>searxng', $search->name());

        $broken = new FallbackSearch([new BraveProvider(FakeHttp::client(new FakeHttp([[500, 'x']])), 'brave-secret')]);
        try {
            $broken->search('x');
            self::fail('expected a failure');
        } catch (SearchException $e) {
            self::assertStringContainsString('Brave: HTTP 500', $e->getMessage());
            self::assertStringNotContainsString('brave-secret', $e->getMessage());
        }
    }

    public function testTheSameQueryIsAnsweredFromTheCacheUntilItExpires(): void {
        $fake = new FakeHttp([
            [200, self::json(['results' => [['title' => 'one', 'url' => 'https://one.example', 'content' => '']]])],
            [200, self::json(['results' => [['title' => 'two', 'url' => 'https://two.example', 'content' => '']]])],
        ]);
        $now = 1000.0;
        $search = new CachedSearch(new SearxngProvider(FakeHttp::client($fake), 'http://s'), 600.0, 10, function () use (&$now): float {
            return $now;
        });

        self::assertSame('one', $search->search('PHP  8.6')->hits[0]->title ?? null);
        self::assertSame('one', $search->search('php 8.6')->hits[0]->title ?? null, 'case and spaces do not matter');
        $now += 601.0;
        self::assertSame('two', $search->search('php 8.6')->hits[0]->title ?? null);
        self::assertCount(2, $fake->requests);
    }
}
