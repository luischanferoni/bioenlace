<?php

use yii\db\Migration;

/**
 * Grupos de atajos del asistente (etiqueta + orden por prefijo de intent_id).
 *
 * Membresía de intents: RBAC (auth_item_child). Esta tabla solo presentación.
 */
class m260812_120000_assistant_shortcut_group_table extends Migration
{
    public function safeUp(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $table = '{{%assistant_shortcut_group}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'group_id' => $this->string(64)->notNull(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->addPrimaryKey('pk_assistant_shortcut_group', $table, 'group_id');

        $now = date('Y-m-d H:i:s');
        $rows = [
            ['atencion', 'Atención', 10],
            ['turnos', 'Turnos', 20],
            ['urgencias', 'Urgencias / guardia', 30],
            ['internacion', 'Internación', 40],
            ['condicion-laboral', 'Condición laboral', 50],
            ['profesional-agenda', 'Agenda', 60],
            ['profesional-cobertura', 'Cobertura', 70],
            ['profesional-efector-servicio', 'Personal en el efector', 80],
            ['profesional-horarios', 'Horarios', 90],
            ['profesionales', 'Profesionales del efector', 100],
            ['licencia', 'Licencias', 110],
            ['tratamiento', 'Tratamiento', 120],
            ['laboratorio', 'Laboratorio', 130],
            ['receta', 'Recetas', 140],
            ['personas', 'Representación', 150],
            ['plataforma', 'Ayuda y plataforma', 160],
            ['paciente-contexto', 'Mi provincia', 170],
        ];

        foreach ($rows as [$groupId, $label, $sortOrder]) {
            $this->insert($table, [
                'group_id' => $groupId,
                'label' => $label,
                'sort_order' => $sortOrder,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $this->dropTable('{{%assistant_shortcut_group}}');
    }
}
