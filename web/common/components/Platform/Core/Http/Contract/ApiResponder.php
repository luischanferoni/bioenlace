<?php

namespace common\components\Platform\Core\Http\Contract;

/**
 * Forma única de respuesta de la API v1.
 *
 * Éxito: {data, meta}. Error: {error: {code, message, intent, retryable, field_errors?}}.
 */
final class ApiResponder
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     * @return array{data: mixed, meta: object}
     */
    public static function success($data = null, array $meta = []): array
    {
        return ['data' => $data, 'meta' => (object) $meta];
    }

    /**
     * @param array<string, scalar> $params
     * @param array<string, list<string>> $fieldErrors
     * @param array<string, mixed> $meta
     * @return array{body: array<string, mixed>, status: int}
     */
    public static function error(string $code, array $params = [], array $fieldErrors = [], array $meta = []): array
    {
        $resolved = ApiErrorCatalog::resolve($code, $params);
        $error = [
            'code' => $resolved['code'],
            'message' => $resolved['message'],
            'intent' => $resolved['intent'],
            'retryable' => $resolved['retryable'],
        ];
        if ($fieldErrors !== []) {
            $error['field_errors'] = $fieldErrors;
        }

        $body = ['error' => $error];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return ['body' => $body, 'status' => $resolved['http_status']];
    }

    /**
     * Estado visible al usuario: el cliente solo pinta `label` con el estilo de `intent`.
     *
     * @return array{code: string, label: string, intent: string}
     */
    public static function status(string $code, string $label, string $intent = 'info'): array
    {
        return ['code' => $code, 'label' => $label, 'intent' => $intent];
    }
}
