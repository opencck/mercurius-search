<?php

declare(strict_types=1);

namespace Mercurius\Search\Internal;

use Amp\Cancellation;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Mercurius\Search\SearchException;
use Throwable;

/**
 * @internal one JSON request with a timeout, its errors as SearchException without secrets
 */
final class Json {
    /** @return array<array-key, mixed> */
    public static function request(HttpClient $http, Request $request, string $label, ?Cancellation $cancellation, float $timeout = 30.0): array {
        $request->setTransferTimeout($timeout);
        $request->setInactivityTimeout($timeout);
        $request->setHeader('accept', 'application/json');
        try {
            $response = $http->request($request, $cancellation ?? new \Amp\NullCancellation());
            $body = $response->getBody()->buffer($cancellation);
        } catch (Throwable $e) {
            throw new SearchException(sprintf('%s не ответил (%s)', $label, (new \ReflectionClass($e))->getShortName()));
        }
        if ($response->getStatus() !== 200) {
            throw new SearchException(sprintf('%s: HTTP %d', $label, $response->getStatus()));
        }
        try {
            /** @var mixed $data */
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SearchException($label . ': ответ не JSON');
        }
        if (!is_array($data)) {
            throw new SearchException($label . ': ответ не объект');
        }
        return $data;
    }

    /** @param array<array-key, mixed> $row */
    public static function str(array $row, string $key): string {
        return isset($row[$key]) && is_string($row[$key]) ? trim($row[$key]) : '';
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<array<array-key, mixed>>
     */
    public static function rows(array $data, string ...$path): array {
        $node = $data;
        foreach ($path as $key) {
            if (!isset($node[$key]) || !is_array($node[$key])) {
                return [];
            }
            $node = $node[$key];
        }
        $rows = [];
        /** @var mixed $row */
        foreach ($node as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * @param list<array<array-key, mixed>> $rows
     * @param positive-int $limit
     * @return list<\Mercurius\Search\SearchHit>
     */
    public static function hits(array $rows, string $snippetKey, int $limit): array {
        $hits = [];
        foreach ($rows as $row) {
            $url = self::str($row, 'url');
            if ($url === '' || !preg_match('~^https?://~i', $url)) {
                continue;
            }
            $hits[] = new \Mercurius\Search\SearchHit(self::str($row, 'title'), $url, mb_substr(self::str($row, $snippetKey), 0, 500), count($hits) + 1);
            if (count($hits) >= $limit) {
                break;
            }
        }
        return $hits;
    }
}
