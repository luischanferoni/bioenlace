<?php

namespace common\components\Services\Turnos;

use DateTimeImmutable;
use DateTimeZone;
use Yii;

/**
 * Arma bloques `ui_json` (`kind: list`) para elegir slot: agrupa por día y franja (mañana/tarde),
 * títulos amigables en español y `meta` sin duplicar el id del slot.
 */
final class TurnoSlotOfferUiPresenter
{
    private const WEEKDAYS_ES = [
        0 => 'domingo',
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
    ];

    /**
     * @param array{
     *   por_dia?: list<array{fecha?:string, manana?:list<mixed>, tarde?:list<mixed>}>
     * } $grouped salida de {@see TurnoSlotOfferService::buildGrouped()}
     * @param int $idServicioCriterio id_servicio del pedido (para no repetir servicio en cada ítem)
     * @return list<array<string, mixed>> bloques list listos para `ui_json.blocks`
     */
    public static function buildSlotListBlocks(array $grouped, int $idServicioCriterio): array
    {
        $tz = self::appTimeZone();
        $porDia = isset($grouped['por_dia']) && is_array($grouped['por_dia']) ? $grouped['por_dia'] : [];
        usort($porDia, static function ($a, $b): int {
            $fa = is_array($a) && isset($a['fecha']) ? (string) $a['fecha'] : '';
            $fb = is_array($b) && isset($b['fecha']) ? (string) $b['fecha'] : '';

            return strcmp($fa, $fb);
        });

        $blocks = [];
        $displayOrder = 0;
        foreach ($porDia as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fecha = isset($row['fecha']) ? (string) $row['fecha'] : '';
            if ($fecha === '') {
                continue;
            }
            $dayHead = self::friendlyDayHeadingInternal($fecha, $tz);
            $manana = isset($row['manana']) && is_array($row['manana']) ? $row['manana'] : [];
            $tarde = isset($row['tarde']) && is_array($row['tarde']) ? $row['tarde'] : [];

            $bMan = self::itemsForFranja($fecha, 'manana', $manana, $idServicioCriterio);
            if ($bMan !== []) {
                $blocks[] = self::baseListBlock($displayOrder++, $fecha . '-manana', $dayHead . ' · por la mañana', $bMan);
            }
            $bTar = self::itemsForFranja($fecha, 'tarde', $tarde, $idServicioCriterio);
            if ($bTar !== []) {
                $blocks[] = self::baseListBlock($displayOrder++, $fecha . '-tarde', $dayHead . ' · por la tarde', $bTar);
            }
        }

        return $blocks;
    }

    /**
     * @param list<mixed> $slots
     * @return list<array<string, mixed>>
     */
    private static function itemsForFranja(string $fecha, string $franja, array $slots, int $idServicioCriterio): array
    {
        $out = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $hora = isset($slot['hora']) ? (string) $slot['hora'] : '';
            $idPesSlot = isset($slot['id_profesional_efector_servicio']) && $slot['id_profesional_efector_servicio'] !== null
                ? (int) $slot['id_profesional_efector_servicio']
                : 0;
            if ($hora === '' || $idPesSlot <= 0) {
                continue;
            }
            $slotId = 'pes:' . $idPesSlot . '|' . $fecha . '|' . $hora;
            $label = self::formatHoraLabel($hora);

            $meta = [
                'fecha' => $fecha,
                'hora' => $hora,
                'id_profesional_efector_servicio' => $idPesSlot,
                'franja' => $franja,
            ];
            if (!empty($slot['servicio']) && is_array($slot['servicio'])) {
                $sid = isset($slot['servicio']['id_servicio']) ? (int) $slot['servicio']['id_servicio'] : 0;
                if ($sid > 0 && $sid !== $idServicioCriterio) {
                    $meta['servicio'] = [
                        'id_servicio' => $sid,
                        'nombre' => (string) ($slot['servicio']['nombre'] ?? ''),
                    ];
                }
            }

            $out[] = [
                'id' => $slotId,
                'label' => $label,
                'meta' => $meta,
            ];
        }

        return $out;
    }

    private static function formatHoraLabel(string $hora): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m) === 1) {
            $h = (int) $m[1];
            $min = (int) $m[2];

            return sprintf('%02d:%02d', $h, $min);
        }

        return $hora;
    }

    public static function friendlyDayHeading(string $fechaYmd): string
    {
        return self::friendlyDayHeadingInternal($fechaYmd, self::appTimeZone());
    }

    private static function friendlyDayHeadingInternal(string $fechaYmd, DateTimeZone $tz): string
    {
        $slot = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaYmd, $tz);
        if ($slot === false) {
            return $fechaYmd;
        }
        $today = new DateTimeImmutable('today', $tz);
        $diffDays = (int) floor(($slot->getTimestamp() - $today->getTimestamp()) / 86400);
        if ($diffDays === 0) {
            return 'Hoy';
        }
        if ($diffDays === 1) {
            return 'Mañana';
        }
        if ($diffDays === 2) {
            return 'Pasado mañana';
        }
        $w = (int) $slot->format('w');
        $nombre = self::WEEKDAYS_ES[$w] ?? $slot->format('D');

        return $nombre . ' ' . $slot->format('d/m');
    }

    private static function appTimeZone(): DateTimeZone
    {
        $z = Yii::$app->timeZone;
        if (!is_string($z) || $z === '') {
            $z = 'UTC';
        }

        try {
            return new DateTimeZone($z);
        } catch (\Throwable $e) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    /**
     * @param int $displayOrder orden global de la pantalla (mañana antes que tarde el mismo día; días en orden cronológico)
     */
    private static function baseListBlock(int $displayOrder, string $idSuffix, string $title, array $items): array
    {
        return [
            'kind' => 'list',
            'id' => 'slots-' . $idSuffix,
            'display_order' => $displayOrder,
            'title' => $title,
            'selection' => ['mode' => 'single'],
            'draft_field' => 'slot_id',
            'item' => ['kind' => 'slot', 'id_field' => 'id', 'label_field' => 'label'],
            'items' => $items,
        ];
    }
}
