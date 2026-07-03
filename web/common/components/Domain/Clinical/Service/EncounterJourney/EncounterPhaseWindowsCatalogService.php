<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Catálogo declarativo de ventanas por fase ({@see metadata/encounter_phase_windows.yaml}).
 */
final class EncounterPhaseWindowsCatalogService
{
    private const CATALOG_FILE = 'encounter_phase_windows.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public const PHASE_MOTIVOS = 'motivos_consulta';

    public const PHASE_ASISTENCIA = 'asistencia_pre_consulta';

    public const PHASE_POST = 'post_consulta';

    /**
     * @return list<string>
     */
    public function phaseIds(): array
    {
        $phases = self::load()['phases'] ?? [];

        return is_array($phases) ? array_values(array_map('strval', array_keys($phases))) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function phase(string $phaseId): ?array
    {
        $phaseId = trim($phaseId);
        $phases = self::load()['phases'] ?? [];
        if (!is_array($phases) || !isset($phases[$phaseId]) || !is_array($phases[$phaseId])) {
            return null;
        }

        return $phases[$phaseId];
    }

    /**
     * @return list<array{offset: string, tipo: string, title: string, body: string}>
     */
    public function notifications(string $phaseId): array
    {
        $def = $this->phase($phaseId);
        if ($def === null) {
            return [];
        }
        $raw = $def['notifications'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $offset = trim((string) ($row['offset'] ?? ''));
            $tipo = trim((string) ($row['tipo'] ?? ''));
            if ($offset === '' || $tipo === '') {
                continue;
            }
            $out[] = [
                'offset' => $offset,
                'tipo' => $tipo,
                'title' => trim((string) ($row['title'] ?? '')),
                'body' => trim((string) ($row['body'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Segundos relativos al anchor (negativo = antes, positivo = después).
     */
    public function offsetSeconds(string $offsetExpr): ?int
    {
        $offsetExpr = trim($offsetExpr);
        if ($offsetExpr === '') {
            return null;
        }
        if (str_starts_with($offsetExpr, 'param:')) {
            $key = trim(substr($offsetExpr, 6));
            if ($key === '') {
                return null;
            }
            $minutes = (int) (Yii::$app->params[$key] ?? 0);

            return -max(0, $minutes) * 60;
        }
        if (preg_match('/^([+-]?)(\d+)([hmd])$/i', $offsetExpr, $m) !== 1) {
            return null;
        }
        $sign = -1;
        if ($m[1] === '+') {
            $sign = 1;
        } elseif ($m[1] === '-') {
            $sign = -1;
        } elseif ($offsetExpr[0] === '+') {
            $sign = 1;
        }
        $n = (int) $m[2];
        $unit = strtolower($m[3]);
        $mult = match ($unit) {
            'h' => 3600,
            'd' => 86400,
            default => 60,
        };

        return $sign * $n * $mult;
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = dirname(__DIR__, 2) . '/metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            throw new \RuntimeException('Catálogo encounter_phase_windows no encontrado: ' . $path);
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo encounter_phase_windows inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
