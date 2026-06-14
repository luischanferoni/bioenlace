<?php

namespace common\components\Platform\Core\DataAccess\Edit;

/**
 * Normaliza aspect_ids desde query/post (string CSV o array).
 */
final class EditSparseAspectIds
{
    /**
     * @param mixed $raw aspect_ids, aspect_id o aspect_ids[]
     * @return list<string>
     */
    public static function parse(mixed $raw): array
    {
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $item) {
                $id = trim((string) $item);
                if ($id !== '' && !in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }

            return $out;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/\s*,\s*/', $text) ?: [] as $part) {
            $id = trim((string) $part);
            if ($id !== '' && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<string>
     */
    public static function fromParams(array $params): array
    {
        $ids = self::parse($params['aspect_ids'] ?? null);
        if ($ids !== []) {
            return $ids;
        }

        $single = trim((string) ($params['aspect_id'] ?? ''));
        if ($single !== '') {
            return [$single];
        }

        return [];
    }
}
