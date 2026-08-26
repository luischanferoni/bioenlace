<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Multi-país: geo_paises, id_pais en provincias, vecinos y recursos institucionales en BD.
 *
 * @see web/docs/decisions/runtime-datos-vs-metadata.md
 */
class m260826_120000_geo_multipais extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%geo_provincias}}', true) === null) {
            echo "    > geo_provincias ausente; omitir multi-país.\n";

            return;
        }

        if ($this->db->schema->getTableSchema('{{%geo_paises}}', true) === null) {
            $this->createTable('{{%geo_paises}}', [
                'id_pais' => $this->primaryKey(),
                'iso2' => $this->char(2)->notNull(),
                'nombre' => $this->string(80)->notNull(),
            ]);
            $this->createIndex('ux_geo_paises_iso2', '{{%geo_paises}}', 'iso2', true);
        }

        $this->upsertPais(1, 'AR', 'Argentina');
        $this->upsertPais(2, 'UY', 'Uruguay');

        $provSchema = $this->db->schema->getTableSchema('{{%geo_provincias}}', true);
        if ($provSchema !== null && !isset($provSchema->columns['id_pais'])) {
            $this->addColumn('{{%geo_provincias}}', 'id_pais', $this->integer()->null());
            $this->update('{{%geo_provincias}}', ['id_pais' => 1]);
            $this->alterColumn('{{%geo_provincias}}', 'id_pais', $this->integer()->notNull());
            $this->addForeignKey(
                'fk_geo_provincias_pais',
                '{{%geo_provincias}}',
                'id_pais',
                '{{%geo_paises}}',
                'id_pais',
                'RESTRICT',
                'CASCADE'
            );
        }

        $provSchema = $this->db->schema->getTableSchema('{{%geo_provincias}}', true);
        if ($provSchema !== null && isset($provSchema->columns['cod_indec'])) {
            $size = (int) ($provSchema->columns['cod_indec']->size ?? 0);
            if ($size > 0 && $size < 16) {
                $this->alterColumn('{{%geo_provincias}}', 'cod_indec', $this->string(16)->notNull());
            }
        }

        if (!$this->indexExists('{{%geo_provincias}}', 'ux_geo_provincias_pais_cod')) {
            $this->createIndex(
                'ux_geo_provincias_pais_cod',
                '{{%geo_provincias}}',
                ['id_pais', 'cod_indec'],
                true
            );
        }

        if ($this->db->schema->getTableSchema('{{%geo_provincia_vecinos}}', true) === null) {
            $this->createTable('{{%geo_provincia_vecinos}}', [
                'id_provincia' => $this->integer()->notNull(),
                'id_provincia_vecina' => $this->integer()->notNull(),
                'PRIMARY KEY(id_provincia, id_provincia_vecina)',
            ]);
            $this->addForeignKey(
                'fk_geo_vecinos_provincia',
                '{{%geo_provincia_vecinos}}',
                'id_provincia',
                '{{%geo_provincias}}',
                'id_provincia',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_geo_vecinos_vecina',
                '{{%geo_provincia_vecinos}}',
                'id_provincia_vecina',
                '{{%geo_provincias}}',
                'id_provincia',
                'CASCADE',
                'CASCADE'
            );
        }

        if ($this->db->schema->getTableSchema('{{%geo_recurso_tipos}}', true) === null) {
            $this->createTable('{{%geo_recurso_tipos}}', [
                'tipo' => $this->string(64)->notNull(),
                'PRIMARY KEY(tipo)',
            ]);
        }

        if ($this->db->schema->getTableSchema('{{%geo_recurso_tipo_aliases}}', true) === null) {
            $this->createTable('{{%geo_recurso_tipo_aliases}}', [
                'tipo' => $this->string(64)->notNull(),
                'alias' => $this->string(120)->notNull(),
                'PRIMARY KEY(tipo, alias)',
            ]);
            $this->addForeignKey(
                'fk_geo_recurso_alias_tipo',
                '{{%geo_recurso_tipo_aliases}}',
                'tipo',
                '{{%geo_recurso_tipos}}',
                'tipo',
                'CASCADE',
                'CASCADE'
            );
        }

        if ($this->db->schema->getTableSchema('{{%geo_recursos_institucionales}}', true) === null) {
            $this->createTable('{{%geo_recursos_institucionales}}', [
                'id_recurso' => $this->primaryKey(),
                'id_pais' => $this->integer()->notNull(),
                'id_provincia' => $this->integer()->null(),
                'tipo' => $this->string(64)->notNull(),
                'nombre' => $this->string(200)->notNull(),
                'direccion' => $this->string(255)->null(),
                'telefono' => $this->string(64)->null(),
            ]);
            $this->addForeignKey(
                'fk_geo_recurso_pais',
                '{{%geo_recursos_institucionales}}',
                'id_pais',
                '{{%geo_paises}}',
                'id_pais',
                'RESTRICT',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_geo_recurso_provincia',
                '{{%geo_recursos_institucionales}}',
                'id_provincia',
                '{{%geo_provincias}}',
                'id_provincia',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_geo_recurso_tipo',
                '{{%geo_recursos_institucionales}}',
                'tipo',
                '{{%geo_recurso_tipos}}',
                'tipo',
                'RESTRICT',
                'CASCADE'
            );
            $this->createIndex(
                'ix_geo_recurso_lookup',
                '{{%geo_recursos_institucionales}}',
                ['tipo', 'id_pais', 'id_provincia']
            );
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%geo_recursos_institucionales}}', true) !== null) {
            $this->dropTable('{{%geo_recursos_institucionales}}');
        }
        if ($this->db->schema->getTableSchema('{{%geo_recurso_tipo_aliases}}', true) !== null) {
            $this->dropTable('{{%geo_recurso_tipo_aliases}}');
        }
        if ($this->db->schema->getTableSchema('{{%geo_recurso_tipos}}', true) !== null) {
            $this->dropTable('{{%geo_recurso_tipos}}');
        }
        if ($this->db->schema->getTableSchema('{{%geo_provincia_vecinos}}', true) !== null) {
            $this->dropTable('{{%geo_provincia_vecinos}}');
        }

        $provSchema = $this->db->schema->getTableSchema('{{%geo_provincias}}', true);
        if ($provSchema !== null && isset($provSchema->columns['id_pais'])) {
            if ($this->indexExists('{{%geo_provincias}}', 'ux_geo_provincias_pais_cod')) {
                $this->dropIndex('ux_geo_provincias_pais_cod', '{{%geo_provincias}}');
            }
            $this->dropForeignKey('fk_geo_provincias_pais', '{{%geo_provincias}}');
            $this->dropColumn('{{%geo_provincias}}', 'id_pais');
        }

        if ($this->db->schema->getTableSchema('{{%geo_paises}}', true) !== null) {
            $this->dropTable('{{%geo_paises}}');
        }
    }

    private function upsertPais(int $id, string $iso2, string $nombre): void
    {
        $exists = (new Query())->from('{{%geo_paises}}')->where(['id_pais' => $id])->exists($this->db);
        if ($exists) {
            $this->update('{{%geo_paises}}', ['iso2' => $iso2, 'nombre' => $nombre], ['id_pais' => $id]);

            return;
        }
        $this->insert('{{%geo_paises}}', [
            'id_pais' => $id,
            'iso2' => $iso2,
            'nombre' => $nombre,
        ]);
    }

    private function indexExists(string $table, string $name): bool
    {
        $db = $this->db;
        $tableName = $db->schema->getRawTableName($table);
        $sql = 'SHOW INDEX FROM ' . $db->quoteTableName($tableName) . ' WHERE Key_name = :n';
        $row = $db->createCommand($sql, [':n' => $name])->queryOne();

        return is_array($row);
    }
}
