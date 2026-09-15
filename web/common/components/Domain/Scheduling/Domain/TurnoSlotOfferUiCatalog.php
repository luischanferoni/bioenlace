<?php

namespace common\components\Domain\Scheduling\Domain;

/**
 * Catálogo de dominio (ex Scheduling/metadata/turno_slot_offer_ui.yaml).
 */
final class TurnoSlotOfferUiCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'day_relative_labels' => [
            0 => 'Hoy',
            1 => 'Mañana',
            2 => 'Pasado mañana',
        ],
        'weekdays' => [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sábado',
        ],
        'franja_title_templates' => [
            'manana' => '{day} · por la mañana',
            'tarde' => '{day} · por la tarde',
        ],
        'list_block_defaults' => [
            'selection' => [
                'mode' => 'single',
            ],
            'draft_field' => 'slot_id',
            'item' => [
                'kind' => 'slot',
                'id_field' => 'id',
                'label_field' => 'label',
            ],
            'presentation' => [
                'tile' => 'compact',
                'shape' => 'square',
            ],
        ],
    ];
    }
}
