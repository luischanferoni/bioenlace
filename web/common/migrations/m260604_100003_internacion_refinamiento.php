<?php

use yii\db\Migration;

/**
 * Refinamiento internación: plantillas epicrisis, motivo en cama.
 */
class m260604_100003_internacion_refinamiento extends Migration
{
    public function safeUp()
    {
        $plantilla = '{{%internacion_epicrisis_plantilla}}';
        if ($this->db->schema->getTableSchema($plantilla, true) === null) {
            $this->createTable($plantilla, [
                'id' => $this->primaryKey(),
                'id_efector' => $this->integer()->notNull()->defaultValue(0)
                    ->comment('0 = plantilla global'),
                'id_servicio' => $this->integer()->null()
                    ->comment('Opcional: servicio del efector'),
                'nombre' => $this->string(120)->notNull(),
                'cuerpo' => $this->text()->notNull(),
                'activo' => $this->boolean()->notNull()->defaultValue(true),
                'orden' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->integer()->notNull(),
                'updated_at' => $this->integer()->notNull(),
            ]);
            $this->createIndex('idx_iep_efector_servicio', $plantilla, ['id_efector', 'id_servicio', 'activo']);
        }

        $cama = '{{%infraestructura_cama}}';
        if ($this->db->schema->getTableSchema($cama, true) !== null
            && $this->db->schema->getTableSchema($cama, true)->getColumn('motivo_estado') === null) {
            $this->addColumn($cama, 'motivo_estado', $this->string(255)->null()->after('estado'));
        }

        $now = time();
        $defaults = [
            [
                'nombre' => 'Alta médica estándar',
                'cuerpo' => "Paciente: {paciente}\nIngreso: {fecha_ingreso} · Estadía: {dias_internacion} días\n\nEvolución durante la internación:\n\nMotivo de egreso:\n\nIndicaciones al alta:",
            ],
            [
                'nombre' => 'Alta con seguimiento ambulatorio',
                'cuerpo' => "Paciente: {paciente}\n\nResumen de internación:\n\nPlan de seguimiento ambulatorio:\n- Control en consultorio en ___ días\n- Estudios pendientes:\n\nMedicación al alta:",
            ],
        ];
        foreach ($defaults as $i => $row) {
            $exists = (new \yii\db\Query())
                ->from($plantilla)
                ->where(['id_efector' => 0, 'nombre' => $row['nombre']])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->insert($plantilla, [
                'id_efector' => 0,
                'id_servicio' => null,
                'nombre' => $row['nombre'],
                'cuerpo' => $row['cuerpo'],
                'activo' => 1,
                'orden' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown()
    {
        $cama = '{{%infraestructura_cama}}';
        if ($this->db->schema->getTableSchema($cama, true) !== null
            && $this->db->schema->getTableSchema($cama, true)->getColumn('motivo_estado') !== null) {
            $this->dropColumn($cama, 'motivo_estado');
        }
        $plantilla = '{{%internacion_epicrisis_plantilla}}';
        if ($this->db->schema->getTableSchema($plantilla, true) !== null) {
            $this->dropTable($plantilla);
        }
    }
}
