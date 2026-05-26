<?php

use yii\db\Migration;

/**
 * Workflow de derivaciones/referencias sobre {@see \common\models\Clinical\ServiceRequest} (category=referral).
 *
 * Reemplaza `consultas_derivaciones` (greenfield sin datos legacy).
 */
class m260526_160002_service_request_referral_workflow extends Migration
{
    public function safeUp()
    {
        $table = '{{%service_request}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            echo "    > service_request no existe — omitido.\n";

            return;
        }

        $this->addColumnIfMissing($table, 'target_efector_id', $this->integer()->null());
        $this->addColumnIfMissing($table, 'target_service_id', $this->integer()->null());
        $this->addColumnIfMissing($table, 'referral_status', $this->string(32)->null()->comment('EN_ESPERA|CON_TURNO|RECHAZADA|RESUELTA'));
        $this->addColumnIfMissing($table, 'responded_encounter_id', $this->integer()->null());
        $this->addColumnIfMissing($table, 'referral_kind', $this->string(32)->null()->comment('PRACTICA|INTERCONSULTA'));
        $this->addColumnIfMissing($table, 'request_kind', $this->string(32)->null());

        $this->createIndexIfMissing('idx_sr_referral_workflow', $table, [
            'category',
            'target_efector_id',
            'target_service_id',
            'referral_status',
        ]);

        $this->execute('DROP VIEW IF EXISTS view_consulta_motivo');

        echo "    > service_request: columnas referral workflow añadidas.\n";
    }

    public function safeDown()
    {
        $table = '{{%service_request}}';
        $cols = [
            'target_efector_id',
            'target_service_id',
            'referral_status',
            'responded_encounter_id',
            'referral_kind',
            'request_kind',
        ];
        foreach ($cols as $col) {
            if ($this->db->schema->getTableSchema($table, true)?->getColumn($col) !== null) {
                $this->dropColumn($table, $col);
            }
        }

        return true;
    }

    private function addColumnIfMissing(string $table, string $column, $type): void
    {
        if ($this->db->schema->getTableSchema($table, true)?->getColumn($column) !== null) {
            return;
        }
        $this->addColumn($table, $column, $type);
    }

    private function createIndexIfMissing(string $name, string $table, array $columns): void
    {
        try {
            $this->createIndex($name, $table, $columns);
        } catch (\Throwable $e) {
            // índice puede existir
        }
    }
}
