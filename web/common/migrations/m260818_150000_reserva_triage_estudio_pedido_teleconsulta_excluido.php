<?php

use yii\db\Migration;

/**
 * Estudio/práctica (acto SNOMED → turno presencial): sin videollamada en paso modalidad.
 */
class m260818_150000_reserva_triage_estudio_pedido_teleconsulta_excluido extends Migration
{
    private const TELE = '{{%reserva_triage_teleconsulta_elegibilidad}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TELE, true) === null) {
            return;
        }

        $codigo = 'estudio_pedido';
        $exists = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM ' . self::TELE . ' WHERE triage_codigo = :c',
            [':c' => $codigo]
        )->queryScalar();

        if ($exists > 0) {
            $this->update(self::TELE, [
                'elegibilidad' => 'excluido',
                'prioridad' => 40,
                'notas' => 'Estudio o práctica — turno presencial en el centro (sin videollamada)',
            ], ['triage_codigo' => $codigo]);

            return;
        }

        $this->insert(self::TELE, [
            'triage_codigo' => $codigo,
            'elegibilidad' => 'excluido',
            'prioridad' => 40,
            'notas' => 'Estudio o práctica — turno presencial en el centro (sin videollamada)',
        ]);
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TELE, true) === null) {
            return;
        }
        $this->delete(self::TELE, ['triage_codigo' => 'estudio_pedido']);
    }
}
