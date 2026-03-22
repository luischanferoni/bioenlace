<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Vuelve a registrar rutas API de mutación de cirugía (agenda) en {{%routes}} si faltan.
 */
class m260322_140000_restore_quirofano_cirugia_mutation_api_routes extends Migration
{
    private const ROUTES = [
        '/api/quirofano/update-cirugia',
        '/api/quirofano/cirugia-estado',
    ];

    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%routes}}', true);
        if ($schema === null) {
            return;
        }
        $hasAllowed = isset($schema->columns['allowed_from_child']);
        foreach (self::ROUTES as $name) {
            if ((new Query())->from('{{%routes}}')->where(['name' => $name])->exists()) {
                continue;
            }
            $row = ['name' => $name];
            if ($hasAllowed) {
                $row['allowed_from_child'] = 0;
            }
            $this->insert('{{%routes}}', $row);
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%routes}}', true) === null) {
            return;
        }
        foreach (self::ROUTES as $name) {
            $this->delete('{{%routes}}', ['name' => $name]);
        }
    }
}
