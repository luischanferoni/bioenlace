<?php

use yii\db\Migration;

/**
 * Tabla `seg_nivel_internacion_practica` (prácticas asociadas a internación).
 *
 * En entornos donde nunca existió el DDL, esta migración la crea con las columnas PES
 * previstas en {@see \common\models\SegNivelInternacionPractica}.
 */
class m260508_000005_create_seg_nivel_internacion_practica extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%seg_nivel_internacion_practica}}', true) !== null) {
            return;
        }

        $parent = $this->db->schema->getTableSchema('{{%seg_nivel_internacion}}', true);
        if ($parent === null) {
            throw new \RuntimeException(
                'No existe la tabla seg_nivel_internacion; crear o restaurar esa tabla antes de seg_nivel_internacion_practica.'
            );
        }

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%seg_nivel_internacion_practica}}', [
            'id' => $this->primaryKey(),
            'conceptId' => $this->string(45)->null(),
            'resultado' => $this->string(255)->null(),
            'informe' => $this->text()->null(),
            'fileName' => $this->string(255)->null(),
            'id_rrhh_solicita' => $this->integer()->null(),
            'id_rrhh_realiza' => $this->integer()->null(),
            'id_internacion' => $this->integer()->notNull(),
            'id_profesional_efector_servicio_solicita' => $this->integer()->null(),
            'id_profesional_efector_servicio_realiza' => $this->integer()->null(),
        ], $tableOptions);

        $this->createIndex('idx_snip_internacion', '{{%seg_nivel_internacion_practica}}', 'id_internacion');
        $this->createIndex(
            'idx_snip_pes_sol_id_profesional_efector_servicio_solicita',
            '{{%seg_nivel_internacion_practica}}',
            'id_profesional_efector_servicio_solicita'
        );
        $this->createIndex(
            'idx_snip_pes_rea_id_profesional_efector_servicio_realiza',
            '{{%seg_nivel_internacion_practica}}',
            'id_profesional_efector_servicio_realiza'
        );

        $this->addForeignKey(
            'fk_snip_internacion',
            '{{%seg_nivel_internacion_practica}}',
            'id_internacion',
            '{{%seg_nivel_internacion}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $pes = $this->db->schema->getTableSchema('{{%profesional_efector_servicio}}', true);
        $rs = $this->db->schema->getTableSchema('{{%rrhh_servicio}}', true);
        if ($pes !== null && $rs !== null) {
            $pickSql = '(SELECT id_rr_hh, MIN(id) AS id_rs FROM {{%rrhh_servicio}} WHERE deleted_at IS NULL GROUP BY id_rr_hh) pick';
            $this->execute(<<<SQL
UPDATE {{%seg_nivel_internacion_practica}} p
INNER JOIN $pickSql ON pick.id_rr_hh = p.id_rrhh_solicita
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET p.id_profesional_efector_servicio_solicita = pes.id
WHERE p.id_rrhh_solicita IS NOT NULL AND p.id_rrhh_solicita <> 0
  AND p.id_profesional_efector_servicio_solicita IS NULL
SQL);
            $this->execute(<<<SQL
UPDATE {{%seg_nivel_internacion_practica}} p
INNER JOIN $pickSql ON pick.id_rr_hh = p.id_rrhh_realiza
INNER JOIN {{%profesional_efector_servicio}} pes ON pes.legacy_rrhh_servicio_id = pick.id_rs AND pes.deleted_at IS NULL
SET p.id_profesional_efector_servicio_realiza = pes.id
WHERE p.id_rrhh_realiza IS NOT NULL AND p.id_rrhh_realiza <> 0
  AND p.id_profesional_efector_servicio_realiza IS NULL
SQL);
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%seg_nivel_internacion_practica}}', true) === null) {
            return;
        }
        $this->dropForeignKey('fk_snip_internacion', '{{%seg_nivel_internacion_practica}}');
        $this->dropTable('{{%seg_nivel_internacion_practica}}');
    }
}
