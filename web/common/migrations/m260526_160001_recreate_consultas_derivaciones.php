<?php

use yii\db\Migration;

/**
 * Recrea `consultas_derivaciones` sin FK a `consultas` (solo si fue eliminada por 150002).
 *
 * Puente hasta 03e-1 (derivaciones → service_request referral). No ejecutar si la tabla ya existe.
 */
class m260526_160001_recreate_consultas_derivaciones extends Migration
{
    public function safeUp()
    {
        $table = '{{%consultas_derivaciones}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            echo "    > consultas_derivaciones ya existe — omitido.\n";

            return;
        }

        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'id_consulta_solicitante' => $this->integer()->notNull()->comment('Encounter id'),
            'id_efector' => $this->integer()->notNull(),
            'id_servicio' => $this->integer()->notNull(),
            'id_profesional_efector_servicio' => $this->integer()->null(),
            'id_respondido' => $this->integer()->null()->comment('Encounter id respuesta'),
            'estado' => $this->string(32)->notNull()->defaultValue('EN_ESPERA'),
            'tipo' => $this->string(32)->null(),
            'tipo_solicitud' => $this->string(32)->null(),
            'codigo' => $this->string(64)->null(),
            'indicaciones' => $this->text()->null(),
            'deleted_at' => $this->dateTime()->null(),
            'deleted_by' => $this->integer()->null(),
        ], $opts);

        $this->createIndex('idx_derivaciones_encounter', $table, 'id_consulta_solicitante');
        $this->createIndex('idx_derivaciones_efector_servicio_estado', $table, ['id_efector', 'id_servicio', 'estado']);

        echo "    > consultas_derivaciones recreada (puente 03e-1).\n";
    }

    public function safeDown()
    {
        echo "    > m260526_160001: safeDown no elimina consultas_derivaciones.\n";

        return true;
    }
}
