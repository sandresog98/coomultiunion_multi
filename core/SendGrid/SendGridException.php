<?php
declare(strict_types=1);

final class SendGridException extends RuntimeException
{
    public int $httpStatus;
    /** @var array<string,mixed>|null */
    public ?array $response;

    /** @param array<string,mixed>|null $response */
    public function __construct(string $message, int $httpStatus = 0, ?array $response = null)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->response   = $response;
    }
}
