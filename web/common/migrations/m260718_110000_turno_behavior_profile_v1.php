<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\Scheduling\PersonaTurnosPerfil;
use common\models\Scheduling\PersonaTurnosPerfilMaterializacion;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;
use common\models\Scheduling\Turno;
use yii\db\Migration;

/**
 * Fases 1–2 perfil comportamiento turnos: eventos canónicos, perfil materializado y motivos de cancelación.
 *
 * @see web/docs/plans/perfil-comportamiento-turnos/
 */
class m260718_110000_turno_behavior_profile_v1 extends Migration
{
    public function safeUp()
    {
        $opts = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->expandTurnoEventoAudit($opts);
        $this->expandEstadoMotivo();
        $this->createPerfilTables($opts);
        $this->createMaterializacionTable($opts);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%persona_turnos_perfil_materializacion}}', true) !== null) {
            $this->dropTable('{{%persona_turnos_perfil_materializacion}}');
        }
        if ($this->db->schema->getTableSchema('{{%persona_turnos_perfil_metrica}}', true) !== null) {
            $this->dropTable('{{%persona_turnos_perfil_metrica}}');
        }
        if ($this->db->schema->getTableSchema('{{%persona_turnos_perfil}}', true) !== null) {
            $this->dropTable('{{%persona_turnos_perfil}}');
        }

        $this->revertEstadoMotivo();
        $this->revertTurnoEventoAudit();
    }

    private function expandTurnoEventoAudit(?string $opts): void
    {
        $table = '{{%turno_evento_audit}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'id_turno' => $this->integer()->notNull(),
                'tipo_evento' => $this->string(64)->notNull(),
                'id_user' => $this->integer()->null(),
                'meta_json' => $this->text()->null(),
                'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ], $opts);
            $this->createIndex('idx_turno_evento_audit_turno', $table, 'id_turno');
            $schema = $this->db->schema->getTableSchema($table, true);
        }

        if ($this->db->driverName === 'mysql' && isset($schema->columns['tipo_evento'])) {
            $this->execute("ALTER TABLE {$table} MODIFY COLUMN `tipo_evento` VARCHAR(64) NOT NULL");
        }

        $add = [
            'id_persona' => $this->integer()->null(),
            'event_code' => $this->string(64)->null(),
            'occurred_at' => $this->dateTime()->null(),
            // Nullable a propósito: filas legacy no inventan actor/calidad.
            'actor_type' => $this->string(20)->null()->comment('PACIENTE|REPRESENTANTE|STAFF|EFECTOR|SISTEMA|EXTERNO'),
            'channel' => $this->string(32)->null(),
            'origin' => $this->string(64)->null(),
            'motivo_normalizado' => $this->string(64)->null(),
            'idempotency_key' => $this->string(160)->null(),
            'attribution_quality' => $this->string(32)->null()->comment('NATIVE|LEGACY_INFERRED'),
            'corrected_event_id' => $this->integer()->null(),
            'id_turno_relacionado' => $this->integer()->null(),
            'related_turno_role' => $this->string(16)->null(),
            'appointment_at' => $this->dateTime()->null(),
            'id_efector' => $this->integer()->null(),
            'id_servicio' => $this->integer()->null(),
            'id_profesional_efector_servicio' => $this->integer()->null(),
            'modalidad' => $this->string(20)->null(),
        ];

        $schema = $this->db->schema->getTableSchema($table, true);
        foreach ($add as $col => $def) {
            if ($schema !== null && !isset($schema->columns[$col])) {
                $this->addColumn($table, $col, $def);
            }
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && isset($schema->columns['idempotency_key'])) {
            if (!$this->hasIndex($table, 'uq_turno_evento_audit_idempotency')) {
                $this->createIndex('uq_turno_evento_audit_idempotency', $table, 'idempotency_key', true);
            }
        }
        if ($schema !== null && isset($schema->columns['id_persona']) && !$this->hasIndex($table, 'idx_turno_evento_audit_persona')) {
            $this->createIndex('idx_turno_evento_audit_persona', $table, 'id_persona');
        }
        if ($schema !== null && isset($schema->columns['event_code']) && !$this->hasIndex($table, 'idx_turno_evento_audit_event_code')) {
            $this->createIndex('idx_turno_evento_audit_event_code', $table, ['event_code', 'occurred_at']);
        }

        // Backfill ligero de columnas nuevas desde legado (no inventa actor).
        if ($this->db->driverName === 'mysql') {
            $this->execute(
                "UPDATE {$table} SET event_code = tipo_evento WHERE event_code IS NULL AND tipo_evento IS NOT NULL"
            );
            $this->execute(
                "UPDATE {$table} SET occurred_at = created_at WHERE occurred_at IS NULL"
            );
            // Las filas previas al contrato no prueban actor ni calidad; se consideran inferidas.
            $this->execute(
                "UPDATE {$table} SET attribution_quality = 'LEGACY_INFERRED'
                 WHERE attribution_quality IS NULL"
            );
        }
    }

    private function revertTurnoEventoAudit(): void
    {
        $table = '{{%turno_evento_audit}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        foreach ([
            'uq_turno_evento_audit_idempotency',
            'idx_turno_evento_audit_persona',
            'idx_turno_evento_audit_event_code',
        ] as $index) {
            if ($this->hasIndex($table, $index)) {
                $this->dropIndex($index, $table);
            }
        }
        $cols = [
            'modalidad',
            'id_profesional_efector_servicio',
            'id_servicio',
            'id_efector',
            'appointment_at',
            'related_turno_role',
            'id_turno_relacionado',
            'corrected_event_id',
            'attribution_quality',
            'idempotency_key',
            'motivo_normalizado',
            'origin',
            'channel',
            'actor_type',
            'occurred_at',
            'event_code',
            'id_persona',
        ];
        foreach ($cols as $col) {
            if (isset($schema->columns[$col])) {
                $this->dropColumn($table, $col);
            }
        }
        if ($this->db->driverName === 'mysql' && isset($schema->columns['tipo_evento'])) {
            $this->execute("ALTER TABLE {$table} MODIFY COLUMN `tipo_evento` VARCHAR(40) NOT NULL");
        }
    }

    private function expandEstadoMotivo(): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }
        $schema = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($schema === null || !isset($schema->columns['estado_motivo'])) {
            return;
        }

        $values = Turno::estadoMotivoValues();
        $quoted = array_map(static function (string $v): string {
            return "'" . str_replace("'", "''", $v) . "'";
        }, $values);
        $this->execute(
            'ALTER TABLE {{%turnos}} MODIFY COLUMN `estado_motivo` ENUM(' . implode(',', $quoted) . ') DEFAULT NULL'
        );
        $this->db->schema->refreshTableSchema('{{%turnos}}');
    }

    private function revertEstadoMotivo(): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }
        $schema = $this->db->schema->getTableSchema('{{%turnos}}', true);
        if ($schema === null || !isset($schema->columns['estado_motivo'])) {
            return;
        }

        // Valores nuevos → médico (compatibilidad segura al bajar).
        $this->execute(
            "UPDATE {{%turnos}} SET estado_motivo = 'CANCELADO_X_MEDICO'
             WHERE estado_motivo IN ('CANCELADO_X_SISTEMA', 'CANCELADO_X_EFECTOR')"
        );

        $this->execute(
            "ALTER TABLE {{%turnos}} MODIFY COLUMN `estado_motivo` ENUM(
                'ERROR_CARGA',
                'CANCELADO_X_PACIENTE',
                'CANCELADO_X_MEDICO',
                'SIN_ATENDER_X_PACIENTE',
                'SIN_ATENDER_X_MEDICO'
            ) DEFAULT NULL"
        );
        $this->db->schema->refreshTableSchema('{{%turnos}}');
    }

    private function createPerfilTables(?string $opts): void
    {
        $perfil = '{{%persona_turnos_perfil}}';
        if ($this->db->schema->getTableSchema($perfil, true) === null) {
            $this->createTable($perfil, [
                'id' => $this->primaryKey(),
                'id_persona' => $this->integer()->notNull(),
                'profile_contract_version' => $this->integer()->notNull(),
                'source_watermark_event_id' => $this->integer()->null(),
                'as_of' => $this->dateTime()->notNull(),
                'completeness_status' => MigrationEnumColumn::mysqlEnum(
                    PersonaTurnosPerfil::completenessStatusValues(),
                    PersonaTurnosPerfil::COMPLETENESS_EMPTY,
                    true,
                    'EMPTY|PARTIAL|COMPLETE'
                ),
                'generated_at' => $this->dateTime()->notNull(),
                'superseded_at' => $this->dateTime()->null(),
                // MySQL permite múltiples NULL y un único 1 por persona/contrato.
                'is_current' => $this->tinyInteger()->null()->defaultValue(1),
            ], $opts);
            $this->createIndex('idx_persona_turnos_perfil_persona', $perfil, ['id_persona', 'superseded_at']);
            $this->createIndex(
                'idx_persona_turnos_perfil_contract',
                $perfil,
                ['id_persona', 'profile_contract_version', 'superseded_at']
            );
            $this->createIndex(
                'uq_persona_turnos_perfil_current',
                $perfil,
                ['id_persona', 'profile_contract_version', 'is_current'],
                true
            );
        }

        $metrica = '{{%persona_turnos_perfil_metrica}}';
        if ($this->db->schema->getTableSchema($metrica, true) === null) {
            $this->createTable($metrica, [
                'id' => $this->primaryKey(),
                'id_perfil' => $this->integer()->notNull(),
                'scope_type' => MigrationEnumColumn::mysqlEnum(
                    PersonaTurnosPerfilMetrica::scopeTypeValues(),
                    PersonaTurnosPerfilMetrica::SCOPE_GLOBAL,
                    true,
                    'GLOBAL|EFECTOR|SERVICIO|MODALIDAD'
                ),
                'scope_id' => $this->string(64)->notNull()->defaultValue(''),
                'window_days' => $this->integer()->notNull(),
                'metric_code' => $this->string(64)->notNull(),
                'numerator' => $this->integer()->notNull()->defaultValue(0),
                'denominator' => $this->integer()->null(),
                'value' => $this->decimal(12, 6)->null(),
                'sample_size' => $this->integer()->notNull()->defaultValue(0),
                'confidence_status' => MigrationEnumColumn::mysqlEnum(
                    PersonaTurnosPerfilMetrica::confidenceStatusValues(),
                    PersonaTurnosPerfilMetrica::CONFIDENCE_NOT_APPLICABLE,
                    true,
                    'OK|INSUFFICIENT_DATA|NOT_APPLICABLE'
                ),
            ], $opts);
            $this->createIndex(
                'uq_persona_turnos_perfil_metrica',
                $metrica,
                ['id_perfil', 'scope_type', 'scope_id', 'window_days', 'metric_code'],
                true
            );
            $this->addForeignKey(
                'fk_persona_turnos_perfil_metrica_perfil',
                $metrica,
                'id_perfil',
                $perfil,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    private function createMaterializacionTable(?string $opts): void
    {
        $table = '{{%persona_turnos_perfil_materializacion}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'profile_contract_version' => $this->integer()->notNull(),
            'last_watermark_event_id' => $this->integer()->null(),
            'last_status' => MigrationEnumColumn::mysqlEnum(
                PersonaTurnosPerfilMaterializacion::statusValues(),
                PersonaTurnosPerfilMaterializacion::STATUS_IDLE,
                true,
                'IDLE|RUNNING|OK|FAILED'
            ),
            'last_run_at' => $this->dateTime()->null(),
            'last_error' => $this->text()->null(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opts);
        $this->createIndex(
            'uq_persona_turnos_perfil_mat_contract',
            $table,
            'profile_contract_version',
            true
        );
    }

    private function hasIndex(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->schema->getTableIndexes($raw, true);

        return isset($indexes[$name]);
    }
}
