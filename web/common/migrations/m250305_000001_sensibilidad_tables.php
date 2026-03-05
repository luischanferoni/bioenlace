<?php

use yii\db\Migration;

/**
 * Tablas para control de sensibilidad del resumen con IA (mapeo SNOMED → categoría, reglas por visor).
 * Ver plan: web/docs/RESUMEN_TIMELINE_PACIENTE_IA.md
 */
class m250305_000001_sensibilidad_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%sensibilidad_categoria}}', [
            'id' => $this->primaryKey(),
            'nombre' => $this->string(100)->notNull()->comment('Nombre de la categoría (ej. violencia_sexual, salud_mental)'),
            'descripcion' => $this->text()->comment('Descripción de la categoría'),
        ]);
        $this->createIndex('idx_sensibilidad_categoria_nombre', '{{%sensibilidad_categoria}}', 'nombre');

        // Mapeo: código SNOMED (de cualquiera de las tablas snomed_*) → id_categoria
        $this->createTable('{{%sensibilidad_mapeo_snomed}}', [
            'id' => $this->primaryKey(),
            'tabla_snomed' => $this->string(50)->notNull()->comment('Tabla origen: hallazgos, medicamentos, motivos_consulta, problemas, procedimientos, sintomas, situacion'),
            'codigo' => $this->string(50)->notNull()->comment('conceptId SNOMED'),
            'id_categoria' => $this->integer()->notNull(),
        ]);
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

        // Regla por visor (servicio o rol): para cada categoría, acción (ver_completo | generalizar | ocultar)
        $this->createTable('{{%sensibilidad_regla_visor}}', [
            'id' => $this->primaryKey(),
            'tipo_visor' => $this->string(20)->notNull()->comment('servicio o rol'),
            'id_visor' => $this->integer()->notNull()->comment('id_servicio o id del rol según tipo_visor'),
            'id_categoria' => $this->integer()->notNull(),
            'accion' => $this->string(20)->notNull()->comment('ver_completo, generalizar, ocultar'),
            'codigo_generalizacion' => $this->string(50)->comment('Código SNOMED para sustituir si accion=generalizar'),
            'etiqueta_generalizacion' => $this->string(255)->comment('Etiqueta para mostrar si accion=generalizar'),
        ]);
        $this->createIndex('idx_sensibilidad_regla_visor_visor_cat', '{{%sensibilidad_regla_visor}}', ['tipo_visor', 'id_visor', 'id_categoria'], true);
        $this->addForeignKey(
            'fk_sensibilidad_regla_categoria',
            '{{%sensibilidad_regla_visor}}',
            'id_categoria',
            '{{%sensibilidad_categoria}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_sensibilidad_regla_categoria', '{{%sensibilidad_regla_visor}}');
        $this->dropTable('{{%sensibilidad_regla_visor}}');
        $this->dropForeignKey('fk_sensibilidad_mapeo_categoria', '{{%sensibilidad_mapeo_snomed}}');
        $this->dropTable('{{%sensibilidad_mapeo_snomed}}');
        $this->dropTable('{{%sensibilidad_categoria}}');
    }
}
