<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A batch "Send to event" or its undo can't proceed. `status` is the HTTP
 * status controllers should answer with (422 bad input, 409 conflict).
 */
class QuestionnaireRoutingException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}
