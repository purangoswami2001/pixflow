<?php

namespace App\Exceptions;

use Exception;

class AiGenerationException extends Exception
{
  public function __construct(
    string $message,
    protected int $httpStatus = 502,
  ) {
    parent::__construct($message);
  }

  public function httpStatus(): int
  {
    return $this->httpStatus;
  }
}
