<?php

use yii\db\Migration;

/**
 * Triage raíz seguimiento_cronico (reemplaza control_cronico / tramite_admin en catálogo v1).
 */
class m260614_100000_reserva_triage_seguimiento_cronico extends Migration
{
    private const TELE = '{{%reserva_triage_teleconsulta_elegibilidad}}';
    private const MAP = '{{%reserva_triage_codigo_servicio}}';

    public function safeUp(): void
    {
        $this->seedTeleconsulta('seguimiento_cronico', 'sugerido', 10, 'Seguimiento crónico — teleconsulta sugerida');
        $this->copiarCodigoServicio('control_cronico', 'seguimiento_cronico');
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TELE, true) !== null) {
            $this->delete(self::TELE, ['triage_codigo' => 'seguimiento_cronico']);
        }
        if ($this->db->schema->getTableSchema(self::MAP, true) !== null) {
            $this->delete(self::MAP, ['triage_codigo' => 'seguimiento_cronico']);
        }
    }

    private function seedTeleconsulta(string $codigo, string $elegibilidad, int $prioridad, ?string $notas): void
    {
        if ($this->db->schema->getTableSchema(self::TELE, true) === null) {
            return;
        }
        $exists = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM ' . self::TELE . ' WHERE triage_codigo = :c',
            [':c' => $codigo]
        )->queryScalar();
        if ($exists > 0) {
            return;
        }
        $this->insert(self::TELE, [
            'triage_codigo' => $codigo,
            'elegibilidad' => $elegibilidad,
            'prioridad' => $prioridad,
            'notas' => $notas,
        ]);
    }

    private function copiarCodigoServicio(string $desde, string $hacia): void
    {
        if ($this->db->schema->getTableSchema(self::MAP, true) === null) {
            return;
        }
        $rows = $this->db->createCommand(
            'SELECT id_servicio, prioridad, notas FROM ' . self::MAP . ' WHERE triage_codigo = :c',
            [':c' => $desde]
        )->queryAll();
        foreach ($rows as $row) {
            $exists = (int) $this->db->createCommand(
                'SELECT COUNT(*) FROM ' . self::MAP . ' WHERE triage_codigo = :c AND id_servicio = :s',
                [':c' => $hacia, ':s' => (int) $row['id_servicio']]
            )->queryScalar();
            if ($exists > 0) {
                continue;
            }
            $this->insert(self::MAP, [
                'triage_codigo' => $hacia,
                'id_servicio' => (int) $row['id_servicio'],
                'prioridad' => (int) $row['prioridad'],
                'notas' => $row['notas'],
            ]);
        }
    }
}
