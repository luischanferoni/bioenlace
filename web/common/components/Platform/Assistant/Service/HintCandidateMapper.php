<?php

namespace common\components\Platform\Assistant\Service;

/**
 * Normaliza filas UI JSON / autocomplete al formato de candidatos de hints.
 */
final class HintCandidateMapper
{
    /**
     * @param list<array{id: string, name: string}> $items
     * @return list<array<string, mixed>>
     */
    public static function mapUiJsonItems(array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? trim((string) $row['id']) : '';
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => $name,
                'nombre' => $name,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function mapAutocompleteRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = isset($r['id']) ? trim((string) $r['id']) : '';
            $text = isset($r['text']) ? trim((string) $r['text']) : '';
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => $text !== '' ? $text : $id,
                'nombre' => $text !== '' ? $text : $id,
            ];
        }

        return $out;
    }
}
