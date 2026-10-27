<?php

namespace SignalHouse\SDK\Exceptions;

class ValidationException extends SignalHouseException
{
    public function __construct(string $parameterName)
    {
        parent::__construct("Missing required parameter: {$parameterName}", 400);
    }
}
