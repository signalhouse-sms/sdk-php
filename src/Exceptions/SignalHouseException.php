<?php

namespace SignalHouse\SDK\Exceptions;

class SignalHouseException extends \Exception
{
    protected int $statusCode;

    public function __construct(string $message = '', int $statusCode = 0, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
