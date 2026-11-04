<?php

use yii\db\Migration;

/**
 * Coordenadas del efector elegidas en el mapa del alta comercial.
 */
class m261104_160000_efectores_coordenadas extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%efectores}}', true);
        if ($schema === null) {
            return;
        }
        if ($schema->getColumn('latitud') === null) {
            $this->addColumn('{{%efectores}}', 'latitud', $this->decimal(10, 7)->null());
        }
        if ($schema->getColumn('longitud') === null) {
            $this->addColumn('{{%efectores}}', 'longitud', $this->decimal(10, 7)->null());
        }
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%efectores}}', true);
        if ($schema === null) {
            return;
        }
        if ($schema->getColumn('latitud') !== null) {
            $this->dropColumn('{{%efectores}}', 'latitud');
        }
        if ($schema->getColumn('longitud') !== null) {
            $this->dropColumn('{{%efectores}}', 'longitud');
        }
    }
}
