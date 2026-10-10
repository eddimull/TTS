<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * PandaDoc refused (or failed) to void a contract's document.
 *
 * Carries the upstream HTTP status and body so the failure is diagnosable
 * in Sentry; callers should show the user a generic message instead.
 */
class PandaDocVoidException extends RuntimeException
{
    public function __construct(
        public readonly string $documentId,
        public readonly int $upstreamStatus,
        public readonly string $upstreamBody,
    ) {
        parent::__construct(
            "PandaDoc refused to void document {$documentId} (HTTP {$upstreamStatus}): {$upstreamBody}"
        );
    }
}
