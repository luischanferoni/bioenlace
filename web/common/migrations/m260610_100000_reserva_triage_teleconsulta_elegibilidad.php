<?php

use yii\db\Migration;

/**
 * Elegibilidad clínica de teleconsulta por código de triage (global, paso modalidad).
 *
 * Reemplaza `teleconsulta_elegibilidad` / `suggests_tipo_atencion` del catálogo YAML clínico.
 */
class m260610_100000_reserva_triage_teleconsulta_elegibilidad extends Migration
{
    private const TABLE = '{{%reserva_triage_teleconsulta_elegibilidad}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            $this->createTable(self::TABLE, [
                'id' => $this->primaryKey()->unsigned(),
                'triage_codigo' => $this->string(64)->notNull(),
                'elegibilidad' => $this->string(32)->notNull()->comment('excluido|presencial_preferido|permitido|sugerido'),
                'prioridad' => $this->smallInteger()->notNull()->defaultValue(100),
                'notas' => $this->text()->null(),
            ]);
            $this->createIndex(
                'uq_reserva_triage_teleconsulta_elegibilidad_codigo',
                self::TABLE,
                'triage_codigo',
                true
            );
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
        $this->dropTable(self::TABLE);
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: string|null}>
     */
    private function seedRows(): array
    {
        return [
            ['control_cronico', 'sugerido', 10, 'Control crónico — teleconsulta sugerida'],
            ['tramite_admin', 'permitido', 10, 'Trámite administrativo'],
            ['alarma_fiebre_alta', 'presencial_preferido', 20, 'Fiebre alta — preferir presencial'],
            ['det_pecho_dolor', 'presencial_preferido', 50, 'Dolor torácico'],
            ['det_musculo_esfuerzo', 'sugerido', 50, 'Dolor muscular leve'],
            ['det_musculo_esfuerzo_brazo', 'sugerido', 50, null],
            ['det_musculo_esfuerzo_pierna', 'sugerido', 50, null],
        ];
    }
}
