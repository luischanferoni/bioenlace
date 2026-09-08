<?php

use yii\db\Migration;

/**
 * One-shot histórico: grants analisis / front_ver_historial_paciente → capabilities encounter.
 * Migración legacy ya aplicada; safeUp es no-op (servicio y YAML retirados).
 */
class m260814_140000_encounter_legacy_capability_grants extends Migration
{
    public function safeUp(): void
    {
    }

    public function safeDown(): void
    {
    }
}
