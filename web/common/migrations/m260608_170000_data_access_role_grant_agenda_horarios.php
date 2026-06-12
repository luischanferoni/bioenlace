<?php

use yii\db\Migration;

/**
 * Grant write sobre ProfesionalEfectorServicioAgenda.configuracion (formulario declarativo en catálogo).
 */
class m260608_170000_data_access_role_grant_agenda_horarios extends Migration
{
    private const TABLE = '{{%data_access_role_grant}}';

    public function safeUp()
    {
        $this->upsert(
            self::TABLE,
            [
                'role_name' => 'AdminEfector',
                'entity_group_key' => 'ProfesionalEfectorServicioAgenda.configuracion',
                'operations_csv' => 'write',
                'scope_checker' => 'efector_sesion',
                'active' => 1,
                'notas' => 'Edición agenda vía data-access.editar',
            ],
            [
                'operations_csv' => 'write',
                'scope_checker' => 'efector_sesion',
                'active' => 1,
            ]
        );
    }

    public function safeDown()
    {
        $this->delete(self::TABLE, [
            'role_name' => 'AdminEfector',
            'entity_group_key' => 'ProfesionalEfectorServicioAgenda.configuracion',
        ]);
    }
}
