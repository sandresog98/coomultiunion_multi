<?php
declare(strict_types=1);

/**
 * Excepción para límites del chat (cupo diario / rate-limit).
 * El endpoint la mapea a HTTP 429 (Too Many Requests).
 */
final class ChatLimitException extends RuntimeException
{
}
