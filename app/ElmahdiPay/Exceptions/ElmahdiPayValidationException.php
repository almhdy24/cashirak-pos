<?php

declare(strict_types=1);

namespace ElmahdiPay\Exceptions;

class ElmahdiPayValidationException extends ElmahdiPayException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 0, '', []);
    }
}
