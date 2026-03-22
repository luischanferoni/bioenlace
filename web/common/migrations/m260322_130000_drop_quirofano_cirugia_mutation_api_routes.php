<?php

use yii\db\Migration;

/**
 * Quita rutas API de mutación de cirugía (PATCH); la persistencia pasa por flujo de consulta.
 */
class m260322_130000_drop_quirofano_cirugia_mutation_api_routes extends Migration
{
    private const ROUTES = [
        '/api/quirofano/update-cirugia',
        '/api/quirofano/cirugia-estado',
    ];

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%routes}}', true) === null) {
            return;
        }
        foreach (self::ROUTES as $name) {
            $this->delete('{{%routes}}', ['name' => $name]);
        }
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%routes}}', true);
        if ($schema === null) {
            return;
        }
        $hasAllowed = isset($schema->columns['allowed_from_child']);
        foreach (self::ROUTES as $name) {
            $row = ['name' => $name];
            if ($hasAllowed) {
                $row['allowed_from_child'] = 0;
            }
            $this->insert('{{%routes}}', $row);
        }
    }
}
