<?php

declare(strict_types=1);

namespace App\Support;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public static function send(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode(
            $body,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        exit;
    }
}
