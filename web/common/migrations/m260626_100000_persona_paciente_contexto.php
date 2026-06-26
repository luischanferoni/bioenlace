<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\Person\PersonaPacienteContexto;
use yii\db\Migration;

/**
 * Contexto operativo persistente del paciente (sector salud, provincia, verificación domicilio RENAPER).
 */
class m260626_100000_persona_paciente_contexto extends Migration
{
    public function safeUp()
    {
        $table = '{{%persona_paciente_contexto}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id_persona' => $this->integer()->notNull(),
            'sector_salud' => MigrationEnumColumn::mysqlEnum(
                PersonaPacienteContexto::sectorSaludValues(),
                PersonaPacienteContexto::SECTOR_SALUD_PUBLICO,
                true,
                'PUBLICO|PRIVADO'
            ),
            'id_provincia_contexto' => $this->integer()->null(),
            'domicilio_estado' => MigrationEnumColumn::mysqlEnum(
                PersonaPacienteContexto::domicilioEstadoValues(),
                PersonaPacienteContexto::DOMICILIO_PENDIENTE,
                true,
                'PENDIENTE|VERIFICADO|REQUIERE_PROVINCIA_MANUAL'
            ),
            'domicilio_verificacion_inicio' => $this->dateTime()->notNull(),
            'domicilio_ultimo_intento' => $this->dateTime()->null(),
            'domicilio_intentos' => $this->integer()->notNull()->defaultValue(0),
            'provincia_contexto_manual' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->addPrimaryKey('pk_persona_paciente_contexto', $table, 'id_persona');
        $this->addForeignKey(
            'fk_persona_paciente_contexto_persona',
            $table,
            'id_persona',
            '{{%personas}}',
            'id_persona',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_persona_paciente_contexto_provincia',
            $table,
            'id_provincia_contexto',
            '{{%provincias}}',
            'id_provincia',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $table = '{{%persona_paciente_contexto}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        $this->dropForeignKey('fk_persona_paciente_contexto_provincia', $table);
        $this->dropForeignKey('fk_persona_paciente_contexto_persona', $table);
        $this->dropTable($table);
    }
}
