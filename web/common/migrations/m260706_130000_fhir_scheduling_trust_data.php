<?php

use yii\db\Migration;

/**
 * Datos de confianza para agendamiento FHIR entrante:
 * - personas.cuil
 * - integration_fhir_service_code
 * - integration_schedule_link
 */
class m260706_130000_fhir_scheduling_trust_data extends Migration
{
    private const FK_IFSC_SERVICIO = 'fk_ifsc_servicio';
    private const FK_ISL_PES = 'fk_isl_pes';

    public function safeUp()
    {
        $personas = '{{%personas}}';
        $schema = $this->db->schema->getTableSchema($personas, true);
        if ($schema !== null && !isset($schema->columns['cuil'])) {
            $this->addColumn($personas, 'cuil', $this->char(11)->null()->comment('CUIL sin guiones'));
            $this->createIndex('ux_personas_cuil', $personas, 'cuil', true);
        }

        $this->ensureIntegrationFhirServiceCodeTable();
        $this->ensureIntegrationScheduleLinkTable();
    }

    public function safeDown()
    {
        $scheduleLink = '{{%integration_schedule_link}}';
        if ($this->db->schema->getTableSchema($scheduleLink, true) !== null) {
            if ($this->foreignKeyExists($scheduleLink, self::FK_ISL_PES)) {
                $this->dropForeignKey(self::FK_ISL_PES, $scheduleLink);
            }
            $this->dropTable($scheduleLink);
        }

        $serviceCode = '{{%integration_fhir_service_code}}';
        if ($this->db->schema->getTableSchema($serviceCode, true) !== null) {
            if ($this->foreignKeyExists($serviceCode, self::FK_IFSC_SERVICIO)) {
                $this->dropForeignKey(self::FK_IFSC_SERVICIO, $serviceCode);
            }
            $this->dropTable($serviceCode);
        }

        $personas = '{{%personas}}';
        $schema = $this->db->schema->getTableSchema($personas, true);
        if ($schema !== null && isset($schema->columns['cuil'])) {
            $this->dropIndex('ux_personas_cuil', $personas);
            $this->dropColumn($personas, 'cuil');
        }
    }

    private function ensureIntegrationFhirServiceCodeTable(): void
    {
        $table = '{{%integration_fhir_service_code}}';
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'source_system' => $this->string(64)->notNull()->defaultValue('fhir-default')
                    ->comment('Origen del código (p. ej. hapi-efector-x)'),
                'code_system' => $this->string(256)->notNull()
                    ->comment('URI del code system FHIR'),
                'code_value' => $this->string(64)->notNull(),
                // Debe coincidir con servicios.id_servicio (INT UNSIGNED).
                'id_servicio' => $this->integer()->unsigned()->notNull(),
                'id_efector_scope' => $this->integer()->notNull()->defaultValue(0)
                    ->comment('0 = global; >0 = id_efector'),
                'label' => $this->string(255)->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
                'deleted_at' => $this->dateTime()->null(),
            ], $tableOptions);
            $this->createIndex(
                'ux_integration_fhir_service_code',
                $table,
                ['source_system', 'code_system', 'code_value', 'id_efector_scope'],
                true
            );
            $this->createIndex('idx_ifsc_servicio', $table, 'id_servicio');
        } elseif (isset($schema->columns['id_servicio']) && !$schema->columns['id_servicio']->unsigned) {
            $this->alterColumn($table, 'id_servicio', $this->integer()->unsigned()->notNull());
        }

        if (!$this->foreignKeyExists($table, self::FK_IFSC_SERVICIO)) {
            $this->addForeignKey(
                self::FK_IFSC_SERVICIO,
                $table,
                'id_servicio',
                '{{%servicios}}',
                'id_servicio',
                'RESTRICT',
                'CASCADE'
            );
        }
    }

    private function ensureIntegrationScheduleLinkTable(): void
    {
        $table = '{{%integration_schedule_link}}';
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        if ($this->db->schema->getTableSchema($table, true) === null) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'source_system' => $this->string(64)->notNull(),
                'external_schedule_id' => $this->string(128)->notNull(),
                'id_profesional_efector_servicio' => $this->integer()->notNull(),
                'resolution_method' => $this->string(32)->notNull()->defaultValue('manual')
                    ->comment('manual | composite_v1'),
                'actor_fingerprint' => $this->char(64)->null()->comment('SHA-256 hex de actores FHIR al verificar'),
                'status' => $this->string(16)->notNull()->defaultValue('pending')
                    ->comment('pending | verified | stale | revoked'),
                'verified_at' => $this->dateTime()->null(),
                'verified_by_user_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ], $tableOptions);
            $this->createIndex(
                'ux_integration_schedule_link',
                $table,
                ['source_system', 'external_schedule_id'],
                true
            );
            $this->createIndex('idx_isl_pes', $table, 'id_profesional_efector_servicio');
        }

        if (!$this->foreignKeyExists($table, self::FK_ISL_PES)) {
            $this->addForeignKey(
                self::FK_ISL_PES,
                $table,
                'id_profesional_efector_servicio',
                '{{%profesional_efector_servicio}}',
                'id',
                'RESTRICT',
                'CASCADE'
            );
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return false;
        }

        $raw = $this->db->schema->getRawTableName($table);
        $cnt = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND CONSTRAINT_NAME = :n
               AND CONSTRAINT_TYPE = :type',
            [':t' => $raw, ':n' => $name, ':type' => 'FOREIGN KEY']
        )->queryScalar();

        return $cnt > 0;
    }
}
