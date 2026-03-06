<?php

use yii\db\Migration;

/**
 * Tablas para control de sensibilidad del resumen con IA (mapeo SNOMED → categoría, regla por categoría con "generalizar para [servicios]").
 * Ver plan: web/docs/RESUMEN_TIMELINE_PACIENTE_IA.md
 * Regla: por categoría, una acción (generalizar|ocultar) y una lista de servicios que reciben esa acción; el resto ve completo.
 */
class m250305_000001_sensibilidad_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%sensibilidad_categoria}}', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(100)->notNull()->comment('Nombre de la categoría (ej. violencia_sexual, salud_mental)'),
            'descripcion' => $this->text()->comment('Descripción de la categoría'),
        ], $tableOptions);
        $this->createIndex('idx_sensibilidad_categoria_nombre', '{{%sensibilidad_categoria}}', 'nombre');

        // Mapeo: código SNOMED (de cualquiera de las tablas snomed_*) → id_categoria
        $this->createTable('{{%sensibilidad_mapeo_snomed}}', [
            'id' => $this->primaryKey(),
            'tabla_snomed' => $this->string(50)->notNull()->comment('Tabla origen: hallazgos, medicamentos, motivos_consulta, problemas, procedimientos, sintomas, situacion'),
            'codigo' => $this->string(50)->notNull()->comment('conceptId SNOMED'),
            'id_categoria' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx_sensibilidad_mapeo_tabla_codigo', '{{%sensibilidad_mapeo_snomed}}', ['tabla_snomed', 'codigo'], true);
        $this->addForeignKey(
            'fk_sensibilidad_mapeo_categoria',
            '{{%sensibilidad_mapeo_snomed}}',
            'id_categoria',
            '{{%sensibilidad_categoria}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Regla por categoría: acción (generalizar|ocultar). Los servicios en sensibilidad_regla_servicio reciben esa acción; el resto ve completo.
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

        // Servicios para los que aplica la acción (generalizar/ocultar). Lista vacía = nadie restringido = todos ven completo.
        $this->createTable('{{%sensibilidad_regla_servicio}}', [
            'id' => $this->primaryKey(),
            'id_regla' => $this->integer()->notNull(),
            'id_servicio' => $this->integer()->unsigned()->notNull()->comment('Servicio que ve generalizado/oculto según la regla'),
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
        $this->dropForeignKey('fk_sensibilidad_mapeo_categoria', '{{%sensibilidad_mapeo_snomed}}');
        $this->dropTable('{{%sensibilidad_mapeo_snomed}}');
        $this->dropTable('{{%sensibilidad_categoria}}');
    }
}
