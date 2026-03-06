<?php

use yii\db\Migration;

/**
 * Transición: reemplaza sensibilidad_regla_visor por sensibilidad_regla + sensibilidad_regla_servicio.
 * Para BDs que ya ejecutaron la migración antigua con regla_visor.
 * Nuevo modelo: por categoría una regla con acción (generalizar|ocultar) y lista de servicios que la reciben; el resto ve completo.
 */
class m250306_000003_sensibilidad_regla_por_servicio extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%sensibilidad_regla}}', true) !== null) {
            return; // Ya aplicado (nuevo esquema)
        }

        if ($this->db->schema->getTableSchema('{{%sensibilidad_regla_visor}}', true) !== null) {
            $this->dropForeignKey('fk_sensibilidad_regla_categoria', '{{%sensibilidad_regla_visor}}');
            $this->dropTable('{{%sensibilidad_regla_visor}}');
        }

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%sensibilidad_regla}}', [
            'id' => $this->primaryKey(),
            'id_categoria' => $this->integer()->notNull(),
            'accion' => $this->string(20)->notNull()->comment('generalizar, ocultar'),
            'codigo_generalizacion' => $this->string(50)->comment('Código SNOMED para sustituir si accion=generalizar'),
            'etiqueta_generalizacion' => $this->string(255)->comment('Etiqueta para mostrar si accion=generalizar'),
        ], $tableOptions);
        $this->createIndex('idx_sensibilidad_regla_categoria', '{{%sensibilidad_regla}}', 'id_categoria', true);
        $this->addForeignKey(
            'fk_sensibilidad_regla_categoria',
            '{{%sensibilidad_regla}}',
            'id_categoria',
            '{{%sensibilidad_categoria}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%sensibilidad_regla_servicio}}', [
            'id' => $this->primaryKey(),
            'id_regla' => $this->integer()->notNull(),
            'id_servicio' => $this->integer()->notNull()->comment('Servicio que ve generalizado/oculto según la regla'),
        ], $tableOptions);
        $this->createIndex('idx_sensibilidad_regla_servicio_regla_servicio', '{{%sensibilidad_regla_servicio}}', ['id_regla', 'id_servicio'], true);
        $this->addForeignKey(
            'fk_sensibilidad_regla_servicio_regla',
            '{{%sensibilidad_regla_servicio}}',
            'id_regla',
            '{{%sensibilidad_regla}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_sensibilidad_regla_servicio_servicio',
            '{{%sensibilidad_regla_servicio}}',
            'id_servicio',
            '{{%servicios}}',
            'id_servicio',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_sensibilidad_regla_servicio_servicio', '{{%sensibilidad_regla_servicio}}');
        $this->dropForeignKey('fk_sensibilidad_regla_servicio_regla', '{{%sensibilidad_regla_servicio}}');
        $this->dropTable('{{%sensibilidad_regla_servicio}}');
        $this->dropForeignKey('fk_sensibilidad_regla_categoria', '{{%sensibilidad_regla}}');
        $this->dropTable('{{%sensibilidad_regla}}');
    }
}
