<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * PandaDoc has not finished producing the signed PDF for a contract yet.
 *
 * Raised when the document is not in `document.completed` state, or when the
 * download endpoint answers 202/409 (file still being generated). Callers
 * should retry later rather than treat this as a permanent failure.
 */
class SignedContractNotReadyException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
