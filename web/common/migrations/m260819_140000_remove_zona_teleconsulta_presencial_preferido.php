<?php

use yii\db\Migration;

/**
 * Revierte filas presencial_preferido por zona (teleconsulta no depende de la zona).
 */
class m260819_140000_remove_zona_teleconsulta_presencial_preferido extends Migration
{
    private const TABLE = '{{%reserva_triage_teleconsulta_elegibilidad}}';

    /** @var list<string> */
    private const ZONAS = [
        'zona_abdomen',
        'zona_musculoesqueletico',
        'zona_piel',
        'zona_sistemas',
        'zona_genitourinario',
    ];

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $this->delete(self::TABLE, [
            'triage_codigo' => self::ZONAS,
            'elegibilidad' => 'presencial_preferido',
        ]);
    }

    public function safeDown(): void
    {
        // Sin re-seed: la elegibilidad por zona fue revertida a nivel producto.
    }
}
