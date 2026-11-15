<?php

declare(strict_types=1);

namespace App\Support;

use JsonException;
use stdClass;

final class JsonRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function body(): array
    {
        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($contentType !== 'application/json') {
            throw new ApiException(
                415,
                'UNSUPPORTED_MEDIA_TYPE',
                'The request content type must be application/json.',
            );
        }

        $rawBody = file_get_contents('php://input');
        try {
            $body = json_decode(
                $rawBody === false ? '' : $rawBody,
                false,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new ApiException(400, 'INVALID_JSON', 'The request body must be valid JSON.');
        }

        if (!$body instanceof stdClass) {
            throw new ApiException(400, 'INVALID_JSON', 'The request body must be a JSON object.');
        }

        return get_object_vars($body);
    }
}
