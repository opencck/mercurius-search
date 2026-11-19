<?php

declare(strict_types=1);

namespace Mercurius\Search;

/**
 * What a search that calls a model cost: the model, its tokens and, when the provider says, the money. A search
 * without a model call (an index, a search API) has none.
 */
final class SearchUsage {
    public function __construct(
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly ?float $costUsd = null,
    ) {}
}
