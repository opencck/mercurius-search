<?php

declare(strict_types=1);

namespace Mercurius\Search;

use Amp\Cancellation;
use InvalidArgumentException;

/**
 * Tries the providers in the order given: a failed one hands over to the next; an empty answer is an answer.
 *
 * ref: references/hermes-agent/tools/web_tools.py@e96221fa:L118-L139 — providers in a set order of preference
 */
final class FallbackSearch implements SearchProvider {
    /** @var non-empty-list<SearchProvider> */
    private readonly array $providers;

    /** @param list<SearchProvider> $providers */
    public function __construct(array $providers) {
        if ($providers === []) {
            throw new InvalidArgumentException('At least one search provider is needed');
        }
        $this->providers = $providers;
    }

    public function name(): string {
        return implode('>', array_map(static fn(SearchProvider $p): string => $p->name(), $this->providers));
    }

    public function search(string $query, int $limit = 5, ?Cancellation $cancellation = null): SearchResult {
        $errors = [];
        foreach ($this->providers as $provider) {
            try {
                return $provider->search($query, $limit, $cancellation);
            } catch (SearchException $e) {
                $cancellation?->throwIfRequested();
                $errors[] = $e->getMessage();
            }
        }
        throw new SearchException('Поиск не удался: ' . implode('; ', $errors));
    }
}
