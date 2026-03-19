<?php

use yii\db\Migration;

/**
 * Plan integral turnos: config por efector, notificaciones programadas, auditoría,
 * extensiones turnos/agenda/user_device, solicitudes entre RRHH, liberación autogestión.
 */
class m260319_000001_turnos_integral_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        if ($this->db->schema->getTableSchema('{{%efector_turnos_config}}', true) === null) {
            $this->createTable('{{%efector_turnos_config}}', [
                'id' => $this->primaryKey(),
                'id_efector' => $this->integer()->notNull()->comment('Efector'),
                'cancel_suave_umbral' => $this->integer()->notNull()->defaultValue(3),
                'cancel_moderada_umbral' => $this->integer()->notNull()->defaultValue(5),
                'cancel_ventana_dias' => $this->integer()->notNull()->defaultValue(90),
                'autogestion_liberacion_vigencia_dias' => $this->integer()->notNull()->defaultValue(180),
                'confirmacion_requerida' => $this->boolean()->notNull()->defaultValue(true),
                'permitir_cambio_modalidad' => $this->boolean()->notNull()->defaultValue(true),
                'recordatorios_habilitados' => $this->boolean()->notNull()->defaultValue(true),
                'modo_comunicacion_medicos' => $this->string(32)->notNull()->defaultValue('deshabilitado'),
                'sobreturno_notificar_retraso' => $this->boolean()->notNull()->defaultValue(true),
                'sobreturno_minutos_retraso_estimado' => $this->integer()->notNull()->defaultValue(30),
                'cancelacion_masiva' => $this->boolean()->notNull()->defaultValue(true),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ], $tableOptions);
            $this->createIndex('ux_efector_turnos_config_efector', '{{%efector_turnos_config}}', 'id_efector', true);
            $this->addForeignKey(
                'fk_efector_turnos_config_efector',
                '{{%efector_turnos_config}}',
                'id_efector',
                '{{%efectores}}',
                'id_efector',
                'CASCADE',
                'CASCADE'
            );
        }

        if ($this->db->schema->getTableSchema('{{%turno_notificacion_programada}}', true) === null) {
            $this->createTable('{{%turno_notificacion_programada}}', [
                'id' => $this->primaryKey(),
                'id_turno' => $this->integer()->notNull(),
                'tipo' => $this->string(40)->notNull(),
                'run_at' => $this->dateTime()->notNull(),
                'estado' => $this->string(20)->notNull()->defaultValue('PENDIENTE'),
                'payload_json' => $this->text()->null(),
                'intentos' => $this->integer()->notNull()->defaultValue(0),
                'ultimo_error' => $this->text()->null(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ], $tableOptions);
            $this->createIndex('idx_turno_notif_run_estado', '{{%turno_notificacion_programada}}', ['run_at', 'estado']);
            $this->createIndex('idx_turno_notif_turno', '{{%turno_notificacion_programada}}', 'id_turno');
        }

        if ($this->db->schema->getTableSchema('{{%turno_evento_audit}}', true) === null) {
            $this->createTable('{{%turno_evento_audit}}', [
                'id' => $this->primaryKey(),
                'id_turno' => $this->integer()->notNull(),
                'tipo_evento' => $this->string(40)->notNull(),
                'id_user' => $this->integer()->null(),
                'meta_json' => $this->text()->null(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ], $tableOptions);
            $this->createIndex('idx_turno_evento_audit_turno', '{{%turno_evento_audit}}', 'id_turno');
        }

        if ($this->db->schema->getTableSchema('{{%persona_efector_autogestion_liberacion}}', true) === null) {
            $this->createTable('{{%persona_efector_autogestion_liberacion}}', [
                'id' => $this->primaryKey(),
                'id_persona' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->notNull(),
                'liberada_en' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'id_user' => $this->integer()->null(),
                'motivo' => $this->string(255)->null(),
            ], $tableOptions);
            $this->createIndex('idx_autogestion_lib_persona_efector', '{{%persona_efector_autogestion_liberacion}}', ['id_persona', 'id_efector']);
        }

        if ($this->db->schema->getTableSchema('{{%solicitud_rrhh}}', true) === null) {
            $this->createTable('{{%solicitud_rrhh}}', [
                'id' => $this->primaryKey(),
                'id_efector' => $this->integer()->notNull(),
                'id_solicitante_rr_hh' => $this->integer()->notNull(),
                'id_destinatario_rr_hh' => $this->integer()->null(),
                'id_intermediario_user' => $this->integer()->null(),
                'estado' => $this->string(32)->notNull()->defaultValue('PENDIENTE'),
                'tipo' => $this->string(64)->notNull()->defaultValue('general'),
                'mensaje' => $this->text()->notNull(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ], $tableOptions);
            $this->createIndex('idx_solicitud_rrhh_efector', '{{%solicitud_rrhh}}', 'id_efector');
            $this->createIndex('idx_solicitud_rrhh_estado', '{{%solicitud_rrhh}}', 'estado');
        }

        if ($this->db->schema->getTableSchema('{{%solicitud_rrhh_evento}}', true) === null) {
            $this->createTable('{{%solicitud_rrhh_evento}}', [
                'id' => $this->primaryKey(),
                'id_solicitud' => $this->integer()->notNull(),
                'id_user' => $this->integer()->null(),
                'tipo' => $this->string(32)->notNull(),
                'detalle' => $this->text()->null(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ], $tableOptions);
            $this->createIndex('idx_solicitud_rrhh_evento_sol', '{{%solicitud_rrhh_evento}}', 'id_solicitud');
            $this->addForeignKey(
                'fk_solicitud_rrhh_evento_sol',
                '{{%solicitud_rrhh_evento}}',
                'id_solicitud',
                '{{%solicitud_rrhh}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos !== null) {
            $cols = $turnos->columns;
            if (!isset($cols['es_sobreturno'])) {
                $this->addColumn('{{%turnos}}', 'es_sobreturno', $this->boolean()->notNull()->defaultValue(false));
            }
            if (!isset($cols['orden_atencion'])) {
                $this->addColumn('{{%turnos}}', 'orden_atencion', $this->integer()->null());
            }
            if (!isset($cols['minutos_desplazamiento_estimado'])) {
                $this->addColumn('{{%turnos}}', 'minutos_desplazamiento_estimado', $this->integer()->null());
            }
            if (!isset($cols['confirmado_en'])) {
                $this->addColumn('{{%turnos}}', 'confirmado_en', $this->dateTime()->null());
            }
            if (!isset($cols['confirmacion_token'])) {
                $this->addColumn('{{%turnos}}', 'confirmacion_token', $this->string(64)->null());
                $this->createIndex('ux_turnos_confirmacion_token', '{{%turnos}}', 'confirmacion_token', true);
            }
        }

        $agenda = $this->db->schema->getTableSchema('{{%agenda_rrhh}}', true);
        if ($agenda !== null && !isset($agenda->columns['duracion_slot_minutos'])) {
            $this->addColumn('{{%agenda_rrhh}}', 'duracion_slot_minutos', $this->integer()->null()->comment('Si no null, anula cálculo de minutos por cupo'));
        }

        $ud = $this->db->schema->getTableSchema('{{%user_device}}', true);
        if ($ud !== null) {
            if (!isset($ud->columns['push_token'])) {
                $this->addColumn('{{%user_device}}', 'push_token', $this->string(512)->null());
            }
            if (!isset($ud->columns['push_provider'])) {
                $this->addColumn('{{%user_device}}', 'push_provider', $this->string(32)->null()->comment('fcm | expo | otro'));
            }
        }
    }

    public function safeDown()
    {
        $ud = $this->db->schema->getTableSchema('{{%user_device}}', true);
        if ($ud !== null) {
            if (isset($ud->columns['push_provider'])) {
                $this->dropColumn('{{%user_device}}', 'push_provider');
            }
            if (isset($ud->columns['push_token'])) {
                $this->dropColumn('{{%user_device}}', 'push_token');
            }
        }

        $agenda = $this->db->schema->getTableSchema('{{%agenda_rrhh}}', true);
        if ($agenda !== null && isset($agenda->columns['duracion_slot_minutos'])) {
            $this->dropColumn('{{%agenda_rrhh}}', 'duracion_slot_minutos');
        }

        $turnos = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($turnos !== null) {
            if (isset($turnos->columns['confirmacion_token'])) {
                $this->dropIndex('ux_turnos_confirmacion_token', '{{%turnos}}');
                $this->dropColumn('{{%turnos}}', 'confirmacion_token');
            }
            foreach (['confirmado_en', 'minutos_desplazamiento_estimado', 'orden_atencion', 'es_sobreturno'] as $c) {
                if (isset($turnos->columns[$c])) {
                    $this->dropColumn('{{%turnos}}', $c);
                }
            }
        }

        if ($this->db->schema->getTableSchema('{{%solicitud_rrhh_evento}}', true) !== null) {
            $this->dropForeignKey('fk_solicitud_rrhh_evento_sol', '{{%solicitud_rrhh_evento}}');
            $this->dropTable('{{%solicitud_rrhh_evento}}');
        }
        if ($this->db->schema->getTableSchema('{{%solicitud_rrhh}}', true) !== null) {
            $this->dropTable('{{%solicitud_rrhh}}');
        }
        if ($this->db->schema->getTableSchema('{{%persona_efector_autogestion_liberacion}}', true) !== null) {
            $this->dropTable('{{%persona_efector_autogestion_liberacion}}');
        }
        if ($this->db->schema->getTableSchema('{{%turno_evento_audit}}', true) !== null) {
            $this->dropTable('{{%turno_evento_audit}}');
        }
        if ($this->db->schema->getTableSchema('{{%turno_notificacion_programada}}', true) !== null) {
            $this->dropTable('{{%turno_notificacion_programada}}');
        }
        if ($this->db->schema->getTableSchema('{{%efector_turnos_config}}', true) !== null) {
            $this->dropForeignKey('fk_efector_turnos_config_efector', '{{%efector_turnos_config}}');
            $this->dropTable('{{%efector_turnos_config}}');
        }
    }
}
