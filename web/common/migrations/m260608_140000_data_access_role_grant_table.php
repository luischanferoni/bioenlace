<?php

use yii\db\Migration;

/**
 * Grants DataAccess por rol (override/suplemento de attribute_groups_v1.yaml).
 */
class m260608_140000_data_access_role_grant_table extends Migration
{
    private const TABLE = '{{%data_access_role_grant}}';

    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->unsigned(),
            'role_name' => $this->string(64)->notNull(),
            'entity_group_key' => $this->string(128)->notNull(),
            'operations_csv' => $this->string(255)->notNull()->comment('read,filter,aggregate'),
            'scope_checker' => $this->string(64)->null(),
            'active' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'notas' => $this->text()->null(),
        ]);

        $this->createIndex(
            'uq_data_access_role_grant_role_group',
            self::TABLE,
            ['role_name', 'entity_group_key'],
            true
        );

        $this->seedFromYamlDefaults();
    }

    public function safeDown(): void
    {
        $this->dropTable(self::TABLE);
    }

    private function seedFromYamlDefaults(): void
    {
        $rows = [
            ['AdminEfector', 'ProfesionalEfectorServicio.asignacion', 'aggregate,filter,read', 'efector_sesion', 'Seed Fase 3'],
            ['AdminEfector', 'Persona.sexo_genero', 'filter,aggregate,read', 'efector_sesion_via_pes', null],
            ['AdminEfector', 'Persona.identidad_basica', 'read', 'efector_sesion_via_pes', 'Listado profesionales'],
            ['Administrativo', 'ProfesionalEfectorServicio.asignacion', 'aggregate,filter,read', 'efector_sesion', null],
            ['Administrativo', 'Persona.sexo_genero', 'filter,aggregate,read', 'efector_sesion_via_pes', null],
            ['Administrativo', 'Persona.identidad_basica', 'read', 'efector_sesion_via_pes', null],
            ['Medico', 'ProfesionalEfectorServicio.asignacion', 'aggregate,filter', 'efector_sesion', null],
        ];

        foreach ($rows as [$role, $group, $ops, $checker, $notas]) {
            $this->insert(self::TABLE, [
                'role_name' => $role,
                'entity_group_key' => $group,
                'operations_csv' => $ops,
                'scope_checker' => $checker,
                'active' => 1,
                'notas' => $notas,
            ]);
        }
    }
}
