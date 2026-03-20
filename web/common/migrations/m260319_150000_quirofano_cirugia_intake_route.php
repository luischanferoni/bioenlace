<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Ruta API intake cirugía (webvimark {{%routes}}).
 */
class m260319_150000_quirofano_cirugia_intake_route extends Migration
{
    private const ROUTE = '/api/quirofano/cirugia-intake';

    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%routes}}', true);
        if ($schema === null) {
            return;
        }
        $exists = (new Query())->from('{{%routes}}')->where(['name' => self::ROUTE])->exists();
        if ($exists) {
            return;
        }
        $row = ['name' => self::ROUTE];
        if (isset($schema->columns['allowed_from_child'])) {
            $row['allowed_from_child'] = 0;
        }
        $this->insert('{{%routes}}', $row);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%routes}}', true) === null) {
            return;
        }
        $this->delete('{{%routes}}', ['name' => self::ROUTE]);
    }
}
