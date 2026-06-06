<?php

use yii\db\Migration;

/**
 * Política de teleconsulta por servicio (especialidad) y allowlist de casos de triage.
 */
class m260607_100000_servicios_teleconsulta_politica extends Migration
{
    public function safeUp()
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema !== null && !isset($schema->columns['teleconsulta_politica'])) {
            $this->addColumn(
                $servicios,
                'teleconsulta_politica',
                $this->string(16)->notNull()->defaultValue('ninguna')->comment('ninguna|todas|algunas')
            );
        }

        $casos = '{{%servicio_teleconsulta_caso}}';
        if ($this->db->schema->getTableSchema($casos, true) === null) {
            $this->createTable($casos, [
                'id' => $this->primaryKey(),
                'id_servicio' => $this->integer()->notNull(),
                'caso_codigo' => $this->string(64)->notNull()->comment('Código del catálogo reserva_triage (ej. control_cronico)'),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ]);
            $this->createIndex(
                'ux_servicio_teleconsulta_caso',
                $casos,
                ['id_servicio', 'caso_codigo'],
                true
            );
            $this->addForeignKey(
                'fk_servicio_teleconsulta_caso_servicio',
                $casos,
                'id_servicio',
                $servicios,
                'id_servicio',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $casos = '{{%servicio_teleconsulta_caso}}';
        if ($this->db->schema->getTableSchema($casos, true) !== null) {
            $this->dropTable($casos);
        }

        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema !== null && isset($schema->columns['teleconsulta_politica'])) {
            $this->dropColumn($servicios, 'teleconsulta_politica');
        }
    }
}
