<?php

namespace App\Exceptions\Consultation;

use RuntimeException;

class ConsultationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
