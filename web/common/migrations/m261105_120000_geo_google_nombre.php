<?php

use yii\db\Migration;

/**
 * Cachea el nombre con el que Google Maps identifica cada entidad geo.
 *
 * Primera vuelta: el nombre de Google se compara normalizado (minúsculas, sin
 * acentos ni prefijos como "Provincia de") contra `nombre`. Cuando hay match se
 * guarda el nombre original de Google en `google_nombre`, así la próxima
 * búsqueda es una igualdad directa y sobrevive a cambios de nomenclatura.
 */
class m261105_120000_geo_google_nombre extends Migration
{
    /** Tablas geo con jerarquía + longitud del nombre de Google que aceptan. */
    private const TABLES = [
        '{{%geo_provincias}}' => 120,
        '{{%geo_departamentos}}' => 150,
        '{{%geo_localidades}}' => 150,
    ];

    public function safeUp()
    {
        foreach (self::TABLES as $table => $length) {
            $schema = $this->db->schema->getTableSchema($table, true);
            if ($schema === null || $schema->getColumn('google_nombre') !== null) {
                continue;
            }
            $this->addColumn(
                $table,
                'google_nombre',
                "VARCHAR($length) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL"
            );
        }
    }

    public function safeDown()
    {
        foreach (array_keys(self::TABLES) as $table) {
            $schema = $this->db->schema->getTableSchema($table, true);
            if ($schema === null || $schema->getColumn('google_nombre') === null) {
                continue;
            }
            $this->dropColumn($table, 'google_nombre');
        }
    }
}
