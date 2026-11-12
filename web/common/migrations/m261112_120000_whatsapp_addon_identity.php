<?php

use common\components\Shared\Infrastructure\Migration\MigrationEnumColumn;
use common\models\Integrations\AsistenteWhatsappAccionPendiente;
use common\models\Integrations\AsistenteWhatsappVerificacion;
use common\models\Integrations\AsistenteWhatsappVinculo;
use yii\db\Migration;

/**
 * Add-on WhatsApp reactivo: verificación inicial, step-up y acciones pendientes.
 *
 * No edita la migración original del vínculo. Los vínculos `ACTIVO` existentes
 * (creados con "SI") se conservan: lectura permitida y step-up en la primera escritura.
 */
class m261112_120000_whatsapp_addon_identity extends Migration
{
    private const LEGACY_ESTADO = 'PENDIENTE_CONFIRMACION';

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $vinculo = '{{%asistente_whatsapp_vinculo}}';
        if ($this->db->schema->getTableSchema($vinculo, true) === null) {
            return;
        }

        $this->ensureVinculoEstadoEnum($vinculo);
        $this->ensureVinculoColumns($vinculo);
        $this->ensureVerificacionTable();
        $this->ensureAccionPendienteTable();
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $vinculo = '{{%asistente_whatsapp_vinculo}}';
        if ($this->db->schema->getTableSchema('{{%asistente_whatsapp_accion_pendiente}}', true) !== null) {
            $this->dropTable('{{%asistente_whatsapp_accion_pendiente}}');
        }
        if ($this->db->schema->getTableSchema('{{%asistente_whatsapp_verificacion}}', true) !== null) {
            $this->dropTable('{{%asistente_whatsapp_verificacion}}');
        }

        if ($this->db->schema->getTableSchema($vinculo, true) !== null) {
            $this->restoreVinculoEstadoEnum($vinculo);

            if ($this->hasIndex($vinculo, 'uq_asistente_whatsapp_vinculo_link_token')) {
                $this->dropIndex('uq_asistente_whatsapp_vinculo_link_token', $vinculo);
            }
            if ($this->hasIndex($vinculo, 'idx_asistente_whatsapp_vinculo_estado')) {
                $this->dropIndex('idx_asistente_whatsapp_vinculo_estado', $vinculo);
            }

            foreach ([
                'last_activity_at',
                'opt_out_at',
                'consent_version',
                'consent_at',
                'last_failed_at',
                'failed_attempts',
                'link_token_expires_at',
                'link_token_hash',
                'verification_expires_at',
                'verified_at',
                'verification_method',
            ] as $column) {
                if ($this->hasColumn($vinculo, $column)) {
                    $this->dropColumn($vinculo, $column);
                }
            }
        }
    }

    private function ensureVinculoEstadoEnum(string $table): void
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns['estado'])) {
            return;
        }
        $dbType = strtoupper((string) $schema->columns['estado']->dbType);
        if ($dbType !== '' && str_contains($dbType, 'BLOQUEADO')) {
            return;
        }

        // 1) Superset temporal para poder migrar las filas viejas sin perderlas.
        $this->execute(
            'ALTER TABLE ' . $table . ' MODIFY `estado` '
            . $this->enumSql(
                array_values(array_unique(array_merge(
                    [self::LEGACY_ESTADO],
                    AsistenteWhatsappVinculo::estadoValues()
                ))),
                AsistenteWhatsappVinculo::ESTADO_PENDIENTE_VERIFICACION
            )
        );

        // 2) Estado viejo → estado nuevo.
        $this->update(
            $table,
            ['estado' => AsistenteWhatsappVinculo::ESTADO_PENDIENTE_VERIFICACION],
            ['estado' => self::LEGACY_ESTADO]
        );

        // 3) Enum final, solo con los valores nuevos.
        $this->execute(
            'ALTER TABLE ' . $table . ' MODIFY `estado` '
            . MigrationEnumColumn::mysqlEnum(
                AsistenteWhatsappVinculo::estadoValues(),
                AsistenteWhatsappVinculo::ESTADO_PENDIENTE_VERIFICACION,
                true,
                'PENDIENTE_VERIFICACION|ACTIVO|RECHAZADO|BLOQUEADO|EXPIRADO'
            )
        );
    }

    private function restoreVinculoEstadoEnum(string $table): void
    {
        $legacy = AsistenteWhatsappVinculo::ESTADO_PENDIENTE_VERIFICACION;
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns['estado'])) {
            return;
        }
        $dbType = strtoupper((string) $schema->columns['estado']->dbType);
        if ($dbType === '' || !str_contains($dbType, 'BLOQUEADO')) {
            return;
        }

        $this->execute(
            'ALTER TABLE ' . $table . ' MODIFY `estado` '
            . $this->enumSql(
                array_values(array_unique(array_merge(
                    [self::LEGACY_ESTADO, $legacy],
                    AsistenteWhatsappVinculo::estadoValues()
                ))),
                $legacy
            )
        );

        $this->update($table, ['estado' => self::LEGACY_ESTADO], ['estado' => $legacy]);
        $this->update(
            $table,
            ['estado' => AsistenteWhatsappVinculo::ESTADO_RECHAZADO],
            ['in', 'estado', [
                AsistenteWhatsappVinculo::ESTADO_BLOQUEADO,
                AsistenteWhatsappVinculo::ESTADO_EXPIRADO,
            ]]
        );

        $this->execute(
            'ALTER TABLE ' . $table . ' MODIFY `estado` '
            . MigrationEnumColumn::mysqlEnum(
                [self::LEGACY_ESTADO, AsistenteWhatsappVinculo::ESTADO_ACTIVO, AsistenteWhatsappVinculo::ESTADO_RECHAZADO],
                self::LEGACY_ESTADO,
                true,
                'PENDIENTE_CONFIRMACION|ACTIVO|RECHAZADO'
            )
        );
    }

    private function ensureVinculoColumns(string $table): void
    {
        $methods = AsistenteWhatsappVinculo::verificationMethodValues();
        MigrationEnumColumn::assertValues($methods, AsistenteWhatsappVinculo::VERIFICATION_METHOD_DIDIT);
        $methodEnum = "ENUM('" . implode("','", $methods) . "') NULL DEFAULT NULL "
            . "COMMENT 'APP_BIOMETRIC|PASSKEY|DIDIT'";

        if (!$this->hasColumn($table, 'verification_method')) {
            $this->addColumn($table, 'verification_method', $methodEnum);
        }
        if (!$this->hasColumn($table, 'verified_at')) {
            $this->addColumn($table, 'verified_at', $this->dateTime()->null());
        }
        if (!$this->hasColumn($table, 'verification_expires_at')) {
            $this->addColumn($table, 'verification_expires_at', $this->dateTime()->null());
        }
        if (!$this->hasColumn($table, 'link_token_hash')) {
            $this->addColumn($table, 'link_token_hash', $this->char(64)->null());
        }
        if (!$this->hasColumn($table, 'link_token_expires_at')) {
            $this->addColumn($table, 'link_token_expires_at', $this->dateTime()->null());
        }
        if (!$this->hasColumn($table, 'failed_attempts')) {
            $this->addColumn($table, 'failed_attempts', $this->integer()->notNull()->defaultValue(0));
        }
        if (!$this->hasColumn($table, 'last_failed_at')) {
            $this->addColumn($table, 'last_failed_at', $this->dateTime()->null());
        }
        if (!$this->hasColumn($table, 'consent_at')) {
            $this->addColumn($table, 'consent_at', $this->dateTime()->null());
        }
        if (!$this->hasColumn($table, 'consent_version')) {
            $this->addColumn($table, 'consent_version', $this->string(32)->null());
        }
        if (!$this->hasColumn($table, 'opt_out_at')) {
            $this->addColumn($table, 'opt_out_at', $this->dateTime()->null());
        }
        if (!$this->hasColumn($table, 'last_activity_at')) {
            $this->addColumn($table, 'last_activity_at', $this->dateTime()->null());
        }

        if (!$this->hasIndex($table, 'uq_asistente_whatsapp_vinculo_link_token')) {
            $this->createIndex(
                'uq_asistente_whatsapp_vinculo_link_token',
                $table,
                ['link_token_hash'],
                true
            );
        }
        if (!$this->hasIndex($table, 'idx_asistente_whatsapp_vinculo_estado')) {
            $this->createIndex(
                'idx_asistente_whatsapp_vinculo_estado',
                $table,
                ['estado', 'verification_expires_at']
            );
        }
    }

    private function ensureVerificacionTable(): void
    {
        $table = '{{%asistente_whatsapp_verificacion}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey()->unsigned(),
            'wa_id' => $this->string(64)->notNull(),
            'user_id' => $this->integer()->null(),
            'id_persona' => $this->integer()->null(),
            'purpose' => MigrationEnumColumn::mysqlEnum(
                AsistenteWhatsappVerificacion::purposeValues(),
                AsistenteWhatsappVerificacion::PURPOSE_LINK,
                true,
                'LINK|STEP_UP'
            ),
            'method' => MigrationEnumColumn::mysqlEnum(
                AsistenteWhatsappVerificacion::methodValues(),
                AsistenteWhatsappVerificacion::METHOD_DIDIT,
                true,
                'APP_BIOMETRIC|PASSKEY|DIDIT'
            ),
            'status' => MigrationEnumColumn::mysqlEnum(
                AsistenteWhatsappVerificacion::statusValues(),
                AsistenteWhatsappVerificacion::STATUS_PENDING,
                true,
                'PENDING|APPROVED|REJECTED|EXPIRED|CONSUMED'
            ),
            'didit_session_id' => $this->string(128)->null(),
            'didit_verification_id' => $this->string(128)->null(),
            'didit_reference_id' => $this->string(128)->null(),
            'linked_document' => $this->string(32)->null(),
            'expires_at' => $this->dateTime()->null(),
            'consumed_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
        ], $this->tableOptions());

        $this->createIndex('idx_wa_verificacion_wa_created', $table, ['wa_id', 'created_at']);
        $this->createIndex('idx_wa_verificacion_didit_session', $table, ['didit_session_id']);
        $this->createIndex('idx_wa_verificacion_status_expires', $table, ['status', 'expires_at']);
    }

    private function ensureAccionPendienteTable(): void
    {
        $table = '{{%asistente_whatsapp_accion_pendiente}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey()->unsigned(),
            'wa_id' => $this->string(64)->notNull(),
            'user_id' => $this->integer()->null(),
            'id_persona' => $this->integer()->null(),
            'intent_id' => $this->string(128)->null(),
            'subintent_id' => $this->string(128)->null(),
            'route' => $this->string(255)->notNull(),
            'method' => $this->string(8)->notNull()->defaultValue('POST'),
            'body_json' => $this->text()->null(),
            'draft_hash' => $this->char(64)->null(),
            'token_hash' => $this->char(64)->notNull(),
            'status' => MigrationEnumColumn::mysqlEnum(
                AsistenteWhatsappAccionPendiente::statusValues(),
                AsistenteWhatsappAccionPendiente::STATUS_PENDING,
                true,
                'PENDING|EXECUTED|REJECTED|EXPIRED'
            ),
            'expires_at' => $this->dateTime()->notNull(),
            'consumed_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->null(),
        ], $this->tableOptions());

        $this->createIndex('uq_wa_accion_token_hash', $table, ['token_hash'], true);
        $this->createIndex('idx_wa_accion_wa_status', $table, ['wa_id', 'status']);
    }

    /**
     * @param list<string> $values
     */
    private function enumSql(array $values, string $default): string
    {
        MigrationEnumColumn::assertValues($values, $default);
        $quoted = array_map(static fn (string $value): string => "'" . $value . "'", $values);

        return 'ENUM(' . implode(',', $quoted) . ") NOT NULL DEFAULT '" . $default . "'";
    }

    private function hasColumn(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function hasIndex(string $table, string $name): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return false;
        }
        foreach ($schema->indexes as $index) {
            if (($index['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    private function tableOptions(): ?string
    {
        return in_array($this->db->driverName, ['mysql', 'mysqli'], true)
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;
    }
}
