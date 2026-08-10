<?php

namespace frontend\components\Clinical;

/**
 * Presentación web del feed `timeline_episodio` (agrupación + labels).
 * El markup vive en partials; el JS solo muestra/oculta por filtro.
 */
final class EpisodioTimelineViewBuilder
{
    private const GROUPABLE_TYPES = [
        'pedido' => true,
        'medicacion' => true,
        'administracion' => true,
        'resultado_lab' => true,
        'interconsulta' => true,
        'atencion_enfermeria' => true,
    ];

    private const TYPE_LABELS = [
        'circuito' => 'Circuito',
        'triage' => 'Triage',
        'evolucion_medica' => 'Evolución',
        'atencion_enfermeria' => 'Enfermería',
        'pedido' => 'Pedido',
        'resultado_lab' => 'Lab',
        'medicacion' => 'Medicación',
        'administracion' => 'Admin.',
        'interconsulta' => 'Interconsulta',
    ];

    private const TYPE_BADGES = [
        'circuito' => 'text-bg-danger',
        'triage' => 'text-bg-danger',
        'evolucion_medica' => 'text-bg-secondary',
        'atencion_enfermeria' => 'text-bg-success',
        'pedido' => 'text-bg-info',
        'resultado_lab' => 'text-bg-info',
        'interconsulta' => 'text-bg-info',
        'medicacion' => 'text-bg-warning',
        'administracion' => 'text-bg-warning',
    ];

    private const STATUS_LABELS = [
        'active' => 'activo',
        'completed' => 'completado',
        'draft' => 'borrador',
        'on-hold' => 'en espera',
        'revoked' => 'anulado',
        'cancelled' => 'cancelado',
        'stopped' => 'detenido',
        'finished' => 'finalizado',
    ];

    /**
     * @param array{items?: list<array<string, mixed>>}|null $feed
     * @return list<array{
     *   type: string,
     *   type_label: string,
     *   badge_class: string,
     *   occurred_at: string,
     *   actor: string,
     *   parts: list<array{text: string, status: string}>
     * }>
     */
    public static function groupsFromFeed(?array $feed): array
    {
        $items = is_array($feed['items'] ?? null) ? $feed['items'] : [];
        $groups = [];

        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $type = (string) ($it['type'] ?? '');
            $actor = '';
            if (isset($it['actor']) && is_array($it['actor']) && !empty($it['actor']['nombre'])) {
                $actor = (string) $it['actor']['nombre'];
            }
            $occurredAt = (string) ($it['occurred_at'] ?? '');
            $timeKey = self::minuteKey($occurredAt);
            $last = $groups !== [] ? $groups[count($groups) - 1] : null;
            $canGroup = isset(self::GROUPABLE_TYPES[$type]);

            if (
                $canGroup
                && $last !== null
                && $last['type'] === $type
                && $last['time_key'] === $timeKey
                && $last['actor'] === $actor
            ) {
                $groups[count($groups) - 1]['parts'][] = self::summaryParts($it);
                continue;
            }

            $groups[] = [
                'type' => $type,
                'type_label' => self::TYPE_LABELS[$type] ?? ($type !== '' ? $type : 'Hito'),
                'badge_class' => self::TYPE_BADGES[$type] ?? 'text-bg-light border text-dark',
                'time_key' => $timeKey,
                'occurred_at' => $occurredAt,
                'actor' => $actor,
                'parts' => [self::summaryParts($it)],
            ];
        }

        return $groups;
    }

    public static function itemCount(?array $feed): int
    {
        $items = is_array($feed['items'] ?? null) ? $feed['items'] : [];

        return count($items);
    }

    /**
     * @return array{text: string, status: string}
     */
    private static function summaryParts(array $it): array
    {
        $text = trim((string) ($it['summary'] ?? ''));
        $status = '';
        $payload = is_array($it['payload'] ?? null) ? $it['payload'] : [];
        if (!empty($payload['status'])) {
            $status = (string) $payload['status'];
        }
        if (preg_match('/\s·\s*(active|completed|draft|on-hold|revoked|cancelled|stopped|finished)\s*$/i', $text, $m)) {
            if ($status === '') {
                $status = $m[1];
            }
            $text = trim(substr($text, 0, -strlen($m[0])));
        }
        if ($status !== '') {
            $key = strtolower($status);
            $status = self::STATUS_LABELS[$key] ?? $status;
        }

        return ['text' => $text, 'status' => $status];
    }

    private static function minuteKey(string $occurredAt): string
    {
        $s = trim($occurredAt);
        if (preg_match('/^(\d{2}\/\d{2}\/\d{4})(?:\s+(\d{2}:\d{2}))?/', $s, $m)) {
            return $m[1] . ' ' . ($m[2] ?? '00:00');
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{1,2}):(\d{2}))?/', $s, $m)) {
            $hh = isset($m[4]) ? str_pad((string) $m[4], 2, '0', STR_PAD_LEFT) : '00';
            $mm = $m[5] ?? '00';

            return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $hh . ':' . $mm;
        }

        return substr($s, 0, 16);
    }
}
