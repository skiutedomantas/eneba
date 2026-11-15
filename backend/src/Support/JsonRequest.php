<?php

declare(strict_types=1);

namespace App\Support;

use JsonException;
use stdClass;

final class JsonRequest
{
    private const MAX_BODY_BYTES = 16384;

    /**
     * @return array<string, mixed>
     */
    public static function body(): array
    {
        $rawBody = file_get_contents(
            'php://input',
            false,
            null,
            0,
            self::MAX_BODY_BYTES + 1,
        );
        if ($rawBody === false) {
            throw new ApiException(400, 'INVALID_JSON', 'The request body could not be read.');
        }

        return self::parse($_SERVER['CONTENT_TYPE'] ?? '', $rawBody);
    }

    /**
     * @return array<string, mixed>
     */
    public static function parse(string $contentType, string $rawBody): array
    {
        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            throw new ApiException(413, 'REQUEST_TOO_LARGE', 'The request body exceeds the allowed size.');
        }

        $contentType = strtolower(trim(explode(';', $contentType)[0]));
        if ($contentType !== 'application/json') {
            throw new ApiException(
                415,
                'UNSUPPORTED_MEDIA_TYPE',
                'The request content type must be application/json.',
            );
        }

        try {
            $body = json_decode(
                $rawBody,
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
