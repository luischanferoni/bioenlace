<?php

use yii\db\Migration;

/**
 * Elimina la ruta API de intake de cirugía (reemplazada por flujo Consulta / Acciones).
 */
class m260322_120000_drop_quirofano_cirugia_intake_route extends Migration
{
    private const ROUTE = '/api/quirofano/cirugia-intake';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%routes}}', true) === null) {
            return;
        }
        $this->delete('{{%routes}}', ['name' => self::ROUTE]);
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%routes}}', true);
        if ($schema === null) {
            return;
        }
        $row = ['name' => self::ROUTE];
        if (isset($schema->columns['allowed_from_child'])) {
            $row['allowed_from_child'] = 0;
        }
        $this->insert('{{%routes}}', $row);
    }
}
