<?php

namespace common\components\Clinical\CareCohort;

/**
 * Extrae JSON estructurado de respuestas IA para packs de cohorte.
 */
final class CarePackContentParser
{
    /**
     * @return array<string, mixed>|null
     */
    public function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $this->normalize($decoded);
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $raw, $m)) {
            $decoded = json_decode(trim($m[1]), true);
            if (is_array($decoded)) {
                return $this->normalize($decoded);
            }
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $this->normalize($decoded);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $data['version'] = (int) ($data['version'] ?? 1);

        return $data;
    }
}
