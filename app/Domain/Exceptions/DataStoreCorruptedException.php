<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * The JSON file on disk cannot be trusted (invalid JSON or unknown schema
 * version). The store keeps a `.bak` copy and refuses to continue instead of
 * silently overwriting the data (plan section 5.7).
 */
final class DataStoreCorruptedException extends DataStoreException {}
