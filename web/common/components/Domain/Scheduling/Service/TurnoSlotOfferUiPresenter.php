<?php

namespace common\components\Domain\Scheduling\Service;

use DateTimeImmutable;
use DateTimeZone;
use Yii;

/**
 * Arma ítems/bloques de oferta de slots a partir de datos agrupados + plantilla JSON / metadata.
 */
final class TurnoSlotOfferUiPresenter
{
    /**
     * @param array{
     *   por_dia?: list<array{fecha?:string, manana?:list<mixed>, tarde?:list<mixed>}>
     * } $grouped
     * @param array<string, mixed>|null $listTemplate bloque `kind: list` de la pantalla JSON (estructura UI)
     * @return list<array<string, mixed>>
     */
    public static function buildSlotListBlocks(
        array $grouped,
        int $idServicioCriterio,
        ?array $listTemplate = null
    ): array {
        $tz = self::appTimeZone();
        $catalog = new TurnoSlotOfferUiCatalogService();
        $template = self::normalizeListTemplate($listTemplate, $catalog);
        $porDia = isset($grouped['por_dia']) && is_array($grouped['por_dia']) ? $grouped['por_dia'] : [];
        usort($porDia, static function ($a, $b): int {
            $fa = is_array($a) && isset($a['fecha']) ? (string) $a['fecha'] : '';
            $fb = is_array($b) && isset($b['fecha']) ? (string) $b['fecha'] : '';

            return strcmp($fa, $fb);
        });

        $blocks = [];
        $displayOrder = 0;
        $idPrefix = trim((string) ($template['id_prefix'] ?? 'slots'));
        if ($idPrefix === '') {
            $idPrefix = 'slots';
        }
        foreach ($porDia as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fecha = isset($row['fecha']) ? (string) $row['fecha'] : '';
            if ($fecha === '') {
                continue;
            }
            $dayHead = self::friendlyDayHeadingInternal($fecha, $tz, $catalog);
            $manana = isset($row['manana']) && is_array($row['manana']) ? $row['manana'] : [];
            $tarde = isset($row['tarde']) && is_array($row['tarde']) ? $row['tarde'] : [];

            $bMan = self::itemsForFranja($fecha, 'manana', $manana, $idServicioCriterio);
            if ($bMan !== []) {
                $blocks[] = self::cloneListBlock(
                    $template,
                    $displayOrder++,
                    $idPrefix . '-' . $fecha . '-manana',
                    $catalog->tituloFranja('manana', $dayHead),
                    $bMan
                );
            }
            $bTar = self::itemsForFranja($fecha, 'tarde', $tarde, $idServicioCriterio);
            if ($bTar !== []) {
                $blocks[] = self::cloneListBlock(
                    $template,
                    $displayOrder++,
                    $idPrefix . '-' . $fecha . '-tarde',
                    $catalog->tituloFranja('tarde', $dayHead),
                    $bTar
                );
            }
        }

        return $blocks;
    }

    /**
     * @param array{por_dia?: list<array{fecha?:string, manana?:list<mixed>, tarde?:list<mixed>}>} $grouped
     * @return list<array{id: string, label: string, meta: array{fecha: string}}>
     */
    public static function buildDayPickerItems(array $grouped): array
    {
        $catalog = new TurnoSlotOfferUiCatalogService();
        $porDia = isset($grouped['por_dia']) && is_array($grouped['por_dia']) ? $grouped['por_dia'] : [];
        usort($porDia, static function ($a, $b): int {
            $fa = is_array($a) && isset($a['fecha']) ? (string) $a['fecha'] : '';
            $fb = is_array($b) && isset($b['fecha']) ? (string) $b['fecha'] : '';

            return strcmp($fa, $fb);
        });

        $items = [];
        foreach ($porDia as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fecha = isset($row['fecha']) ? (string) $row['fecha'] : '';
            if ($fecha === '') {
                continue;
            }
            $manana = isset($row['manana']) && is_array($row['manana']) ? $row['manana'] : [];
            $tarde = isset($row['tarde']) && is_array($row['tarde']) ? $row['tarde'] : [];
            if ($manana === [] && $tarde === []) {
                continue;
            }
            $items[] = [
                'id' => $fecha,
                'label' => self::friendlyDayHeading($fecha, $catalog),
                'meta' => ['fecha' => $fecha],
            ];
        }

        return $items;
    }

    /**
     * Primer bloque `kind: list` de una pantalla ui_json (plantilla estructural).
     *
     * @param array<string, mixed> $uiDefinition
     * @return array<string, mixed>|null
     */
    public static function extractListTemplateFromUi(array $uiDefinition): ?array
    {
        $blocks = $uiDefinition['blocks'] ?? null;
        if (!is_array($blocks)) {
            return null;
        }
        foreach ($blocks as $block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'list') {
                continue;
            }

            return $block;
        }

        return null;
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

    public static function friendlyDayHeading(string $fechaYmd, ?TurnoSlotOfferUiCatalogService $catalog = null): string
    {
        return self::friendlyDayHeadingInternal($fechaYmd, self::appTimeZone(), $catalog ?? new TurnoSlotOfferUiCatalogService());
    }

    private static function friendlyDayHeadingInternal(
        string $fechaYmd,
        DateTimeZone $tz,
        TurnoSlotOfferUiCatalogService $catalog
    ): string {
        $slot = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaYmd, $tz);
        if ($slot === false) {
            return $fechaYmd;
        }
        $today = new DateTimeImmutable('today', $tz);
        $diffDays = (int) floor(($slot->getTimestamp() - $today->getTimestamp()) / 86400);
        $relative = $catalog->labelDiaRelativo($diffDays);
        if ($relative !== null) {
            return $relative;
        }
        $w = (int) $slot->format('w');
        $nombre = $catalog->nombreDiaSemana($w);
        if ($nombre === '') {
            $nombre = $slot->format('D');
        }

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
     * @param array<string, mixed>|null $listTemplate
     * @return array<string, mixed>
     */
    private static function normalizeListTemplate(?array $listTemplate, TurnoSlotOfferUiCatalogService $catalog): array
    {
        $defaults = $catalog->listBlockDefaults();
        $base = is_array($listTemplate) ? $listTemplate : [];
        $id = trim((string) ($base['id'] ?? ''));

        $out = [
            'kind' => 'list',
            'id_prefix' => $id !== '' && $id !== 'default' ? $id : 'slots',
            'selection' => is_array($base['selection'] ?? null)
                ? $base['selection']
                : ($defaults['selection'] ?? ['mode' => 'single']),
            'draft_field' => trim((string) ($base['draft_field'] ?? ($defaults['draft_field'] ?? 'slot_id'))) ?: 'slot_id',
            'item' => is_array($base['item'] ?? null)
                ? $base['item']
                : ($defaults['item'] ?? ['kind' => 'slot', 'id_field' => 'id', 'label_field' => 'label']),
            'presentation' => is_array($base['presentation'] ?? null)
                ? $base['presentation']
                : ($defaults['presentation'] ?? ['tile' => 'compact', 'shape' => 'square']),
        ];
        $empty = trim((string) ($base['empty_message'] ?? ($defaults['empty_message'] ?? '')));
        if ($empty !== '') {
            $out['empty_message'] = $empty;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $template
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function cloneListBlock(
        array $template,
        int $displayOrder,
        string $blockId,
        string $title,
        array $items
    ): array {
        $block = [
            'kind' => 'list',
            'id' => $blockId,
            'display_order' => $displayOrder,
            'title' => $title,
            'selection' => $template['selection'],
            'draft_field' => $template['draft_field'],
            'item' => $template['item'],
            'presentation' => $template['presentation'],
            'items' => $items,
        ];
        if (isset($template['empty_message']) && is_string($template['empty_message']) && $template['empty_message'] !== '') {
            $block['empty_message'] = $template['empty_message'];
        }

        return $block;
    }
}
