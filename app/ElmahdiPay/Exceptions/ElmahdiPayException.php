<?php

declare(strict_types=1);

namespace ElmahdiPay\Exceptions;

class ElmahdiPayException extends \RuntimeException
{
    public function __construct(
        string                  $message,
        private readonly int    $httpStatus   = 0,
        private readonly string $rawResponse  = '',
        private readonly array  $decodedBody  = [],
    ) {
        parent::__construct($message);
    }

    public function getHttpStatus(): int     { return $this->httpStatus;  }
    public function getRawResponse(): string { return $this->rawResponse; }
    public function getDecodedBody(): array  { return $this->decodedBody; }
}
