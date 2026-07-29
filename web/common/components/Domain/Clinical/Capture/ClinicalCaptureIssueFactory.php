<?php

namespace common\components\Domain\Clinical\Capture;

/**
 * Issues de completitud con opciones sugeridas (contrato resumido para clientes).
 *
 * Shape:
 * { id, field, options: [{value,label}], allow_custom }
 * Nada viene seleccionado: la selección es estado del cliente.
 */
final class ClinicalCaptureIssueFactory
{
    /**
     * @param list<array{value: mixed, label: string}> $options
     * @return array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}
     */
    public static function make(
        string $category,
        int $index,
        string $field,
        array $options = [],
        bool $allowCustom = false
    ): array {
        $normalizedOptions = [];
        foreach ($options as $opt) {
            if (!is_array($opt) || !array_key_exists('value', $opt)) {
                continue;
            }
            $label = trim((string) ($opt['label'] ?? $opt['value']));
            $normalizedOptions[] = [
                'value' => $opt['value'],
                'label' => $label !== '' ? $label : (string) $opt['value'],
            ];
        }

        return [
            'id' => self::issueId($category, $index, $field),
            'field' => $field,
            'options' => $normalizedOptions,
            // Solo true si el caller lo pidió explícitamente; sin catálogo no hay issue de texto libre.
            'allow_custom' => $allowCustom,
        ];
    }

    public static function issueId(string $category, int $index, string $field): string
    {
        return trim($category) . '::' . $index . ':' . trim($field);
    }

    /**
     * @return array{category: string, index: int, field: string}|null
     */
    public static function parseIssueId(string $id): ?array
    {
        if (preg_match('/^(.*)::(\d+):(.+)$/u', trim($id), $m) !== 1) {
            return null;
        }

        return [
            'category' => (string) $m[1],
            'index' => (int) $m[2],
            'field' => (string) $m[3],
        ];
    }
}
