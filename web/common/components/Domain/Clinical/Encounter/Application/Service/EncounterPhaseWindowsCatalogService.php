<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Domain\EncounterPhaseWindowsCatalog;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Catálogo declarativo de ventanas por fase ({@see EncounterPhaseWindowsCatalog}).
 */
final class EncounterPhaseWindowsCatalogService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @var array<string, mixed>|null */
    private static ?array $notificationCache = null;

    public const PHASE_MOTIVOS = 'motivos_consulta';

    public const PHASE_MOTIVOS_INTAKE = 'motivos_intake';

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

    public function phaseAnchor(string $phaseId): string
    {
        $def = $this->phase($phaseId);

        return trim((string) ($def['anchor'] ?? 'turno_start')) ?: 'turno_start';
    }

    /**
     * @return list<string>
     */
    public function phaseIdsByAnchor(string $anchor): array
    {
        $anchor = trim($anchor);
        $out = [];
        foreach ($this->phaseIds() as $phaseId) {
            if ($this->phaseAnchor($phaseId) === $anchor) {
                $out[] = $phaseId;
            }
        }

        return $out;
    }

    /**
     * @return list<array{offset: string, tipo: string, title: string, body: string}>
     */
    public function notifications(string $phaseId): array
    {
        $raw = self::loadNotifications()['phases'][$phaseId] ?? [];
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
     * @return array{offset: string, tipo: string, title: string, body: string}|null
     */
    public function notificationByType(string $type): ?array
    {
        foreach ($this->phaseIds() as $phaseId) {
            foreach ($this->notifications($phaseId) as $notification) {
                if ($notification['tipo'] === $type) {
                    return $notification;
                }
            }
        }

        return null;
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
        self::$notificationCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $data = EncounterPhaseWindowsCatalog::config();
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo encounter_phase_windows inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadNotifications(): array
    {
        if (self::$notificationCache !== null) {
            return self::$notificationCache;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Clinical'
            . DIRECTORY_SEPARATOR . 'Encounter'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'encounter-journey-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de encounter: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['phases'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo encounter-journey-notifications inválido.');
        }
        foreach ($data['phases'] as $phaseId => $notifications) {
            if (!is_string($phaseId) || !is_array($notifications)) {
                throw new \RuntimeException('Fase inválida en el catálogo de notificaciones de encounter.');
            }
            foreach ($notifications as $notification) {
                if (
                    !is_array($notification)
                    || !is_string($notification['offset'] ?? null)
                    || trim($notification['offset']) === ''
                    || !is_string($notification['tipo'] ?? null)
                    || trim($notification['tipo']) === ''
                    || !is_string($notification['title'] ?? null)
                    || trim($notification['title']) === ''
                    || !is_string($notification['body'] ?? null)
                    || trim($notification['body']) === ''
                ) {
                    throw new \RuntimeException(
                        'Definición incompleta de notificación en la fase ' . $phaseId . '.'
                    );
                }
            }
        }
        self::$notificationCache = $data;

        return self::$notificationCache;
    }
}
