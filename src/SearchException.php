<?php

declare(strict_types=1);

namespace Mercurius\Search;

use RuntimeException;

/**
 * A search failed: no answer, a bad status or a body that cannot be read. The message never carries a key.
 */
final class SearchException extends RuntimeException {}
