<?php

declare(strict_types=1);

namespace Mercurius\Search\Test;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

/**
 * Answers every request with the next prepared response and keeps the requests.
 */
final class FakeHttp implements DelegateHttpClient {
    /** @var list<Request> */
    public array $requests = [];

    /** @param list<array{int, string}> $responses status and body */
    public function __construct(private array $responses) {}

    public static function client(self $fake): HttpClient {
        return new HttpClient($fake, []);
    }

    public function request(Request $request, Cancellation $cancellation): Response {
        $this->requests[] = $request;
        [$status, $body] = array_shift($this->responses) ?? [500, 'no response prepared'];
        return new Response('1.1', $status, null, ['content-type' => 'application/json'], $body, $request);
    }
}
