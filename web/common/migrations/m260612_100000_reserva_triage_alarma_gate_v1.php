<?php

use yii\db\Migration;

/**
 * Triage v1 (puerta urgencia + grupos): elegibilidad teleconsulta para códigos nuevos.
 */
class m260612_100000_reserva_triage_alarma_gate_v1 extends Migration
{
    private const TABLE = '{{%reserva_triage_teleconsulta_elegibilidad}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            echo "m260612_100000: sin tabla teleconsulta_elegibilidad, omitido.\n";

            return;
        }

        foreach ($this->seedRows() as [$codigo, $elegibilidad, $prioridad, $notas]) {
            $exists = (int) $this->db->createCommand(
                'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE triage_codigo = :c',
                [':c' => $codigo]
            )->queryScalar();
            if ($exists > 0) {
                continue;
            }
            $this->insert(self::TABLE, [
                'triage_codigo' => $codigo,
                'elegibilidad' => $elegibilidad,
                'prioridad' => $prioridad,
                'notas' => $notas,
            ]);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }
        foreach (['alarma_grupo_fiebre_grave'] as $codigo) {
            $this->delete(self::TABLE, ['triage_codigo' => $codigo]);
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: string|null}>
     */
    private function seedRows(): array
    {
        return [
            ['alarma_grupo_fiebre_grave', 'presencial_preferido', 20, 'Fiebre muy alta — preferir presencial'],
        ];
    }
}
