<?php

use yii\db\Migration;

/**
 * Renombra el grupo DataAccess de agenda: ProfesionalEfectorServicio → ProfesionalEfectorServicioAgenda.
 */
class m260620_120000_rename_agenda_data_access_entity_group extends Migration
{
    private const OLD_KEY = 'ProfesionalEfectorServicio.agenda_horarios';

    private const NEW_KEY = 'ProfesionalEfectorServicioAgenda.configuracion';

    public function safeUp()
    {
        $this->update(
            '{{%data_access_role_grant}}',
            ['entity_group_key' => self::NEW_KEY],
            ['entity_group_key' => self::OLD_KEY]
        );
        $this->update(
            '{{%data_access_attribute_field}}',
            ['entity_group_key' => self::NEW_KEY],
            ['entity_group_key' => self::OLD_KEY]
        );
    }

    public function safeDown()
    {
        $this->update(
            '{{%data_access_role_grant}}',
            ['entity_group_key' => self::OLD_KEY],
            ['entity_group_key' => self::NEW_KEY]
        );
        $this->update(
            '{{%data_access_attribute_field}}',
            ['entity_group_key' => self::OLD_KEY],
            ['entity_group_key' => self::NEW_KEY]
        );
    }
}
