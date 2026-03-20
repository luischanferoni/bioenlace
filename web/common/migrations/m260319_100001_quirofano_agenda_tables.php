<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * MVP agenda quirúrgica: salas por efector y cirugías programadas.
 */
class m260319_100001_quirofano_agenda_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        if ($this->db->schema->getTableSchema('{{%quirofano_sala}}', true) === null) {
            $this->createTable('{{%quirofano_sala}}', [
                'id' => $this->primaryKey(),
                'id_efector' => $this->integer()->notNull(),
                'nombre' => $this->string(120)->notNull(),
                'codigo' => $this->string(32)->null(),
                'activo' => $this->boolean()->notNull()->defaultValue(true),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_at' => $this->dateTime()->null(),
                'deleted_by' => $this->integer()->null(),
            ], $tableOptions);
            $this->createIndex('idx_quirofano_sala_efector', '{{%quirofano_sala}}', ['id_efector', 'activo']);
            $this->addForeignKey(
                'fk_quirofano_sala_efector',
                '{{%quirofano_sala}}',
                'id_efector',
                '{{%efectores}}',
                'id_efector',
                'RESTRICT',
                'CASCADE'
            );
        }

        if ($this->db->schema->getTableSchema('{{%cirugia}}', true) === null) {
            $this->createTable('{{%cirugia}}', [
                'id' => $this->primaryKey(),
                'id_quirofano_sala' => $this->integer()->notNull(),
                'id_persona' => $this->integer()->notNull(),
                'id_seg_nivel_internacion' => $this->integer()->null(),
                'id_practica' => $this->integer()->null(),
                'procedimiento_descripcion' => $this->text()->null(),
                'observaciones' => $this->text()->null(),
                'estado' => $this->string(24)->notNull()->defaultValue('LISTA_ESPERA'),
                'fecha_hora_inicio' => $this->dateTime()->notNull(),
                'fecha_hora_fin_estimada' => $this->dateTime()->notNull(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
            ], $tableOptions);
            $this->createIndex('idx_cirugia_sala_inicio', '{{%cirugia}}', ['id_quirofano_sala', 'fecha_hora_inicio']);
            $this->createIndex('idx_cirugia_persona', '{{%cirugia}}', ['id_persona']);
            $this->createIndex('idx_cirugia_estado', '{{%cirugia}}', ['estado']);
            $this->addForeignKey(
                'fk_cirugia_sala',
                '{{%cirugia}}',
                'id_quirofano_sala',
                '{{%quirofano_sala}}',
                'id',
                'RESTRICT',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_cirugia_persona',
                '{{%cirugia}}',
                'id_persona',
                '{{%personas}}',
                'id_persona',
                'RESTRICT',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_cirugia_internacion',
                '{{%cirugia}}',
                'id_seg_nivel_internacion',
                '{{%seg_nivel_internacion}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_cirugia_practica',
                '{{%cirugia}}',
                'id_practica',
                '{{%practicas}}',
                'id_practica',
                'SET NULL',
                'CASCADE'
            );
        }

        $this->seedRbacRoutes();
    }

    /**
     * Registra rutas API para webvimark (tabla {{%routes}}) si existe.
     */
    private function seedRbacRoutes(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%routes}}', true);
        if ($schema === null) {
            return;
        }

        $names = [
            '/api/quirofano/salas',
            '/api/quirofano/view-sala',
            '/api/quirofano/update-sala',
            '/api/quirofano/delete-sala',
            '/api/quirofano/cirugias',
            '/api/quirofano/view-cirugia',
            '/api/quirofano/update-cirugia',
            '/api/quirofano/cirugia-estado',
        ];

        $hasAllowed = isset($schema->columns['allowed_from_child']);
        foreach ($names as $name) {
            $q = (new Query())->from('{{%routes}}')->where(['name' => $name]);
            if ($q->exists()) {
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
        if ($this->db->schema->getTableSchema('{{%cirugia}}', true) !== null) {
            $this->dropForeignKey('fk_cirugia_practica', '{{%cirugia}}');
            $this->dropForeignKey('fk_cirugia_internacion', '{{%cirugia}}');
            $this->dropForeignKey('fk_cirugia_persona', '{{%cirugia}}');
            $this->dropForeignKey('fk_cirugia_sala', '{{%cirugia}}');
            $this->dropTable('{{%cirugia}}');
        }
        if ($this->db->schema->getTableSchema('{{%quirofano_sala}}', true) !== null) {
            $this->dropForeignKey('fk_quirofano_sala_efector', '{{%quirofano_sala}}');
            $this->dropTable('{{%quirofano_sala}}');
        }
    }
}
