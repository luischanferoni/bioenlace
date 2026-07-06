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
    public function safeUp()
    {
        $personas = '{{%personas}}';
        $schema = $this->db->schema->getTableSchema($personas, true);
        if ($schema !== null && !isset($schema->columns['cuil'])) {
            $this->addColumn($personas, 'cuil', $this->char(11)->null()->comment('CUIL sin guiones'));
            $this->createIndex('ux_personas_cuil', $personas, 'cuil', true);
        }

        $serviceCode = '{{%integration_fhir_service_code}}';
        if ($this->db->schema->getTableSchema($serviceCode, true) === null) {
            $this->createTable($serviceCode, [
                'id' => $this->primaryKey(),
                'source_system' => $this->string(64)->notNull()->defaultValue('fhir-default')
                    ->comment('Origen del código (p. ej. hapi-efector-x)'),
                'code_system' => $this->string(256)->notNull()
                    ->comment('URI del code system FHIR'),
                'code_value' => $this->string(64)->notNull(),
                'id_servicio' => $this->integer()->notNull(),
                'id_efector_scope' => $this->integer()->notNull()->defaultValue(0)
                    ->comment('0 = global; >0 = id_efector'),
                'label' => $this->string(255)->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
                'deleted_at' => $this->dateTime()->null(),
            ]);
            $this->createIndex(
                'ux_integration_fhir_service_code',
                $serviceCode,
                ['source_system', 'code_system', 'code_value', 'id_efector_scope'],
                true
            );
            $this->createIndex('idx_ifsc_servicio', $serviceCode, 'id_servicio');
            $this->addForeignKey(
                'fk_ifsc_servicio',
                $serviceCode,
                'id_servicio',
                '{{%servicios}}',
                'id_servicio',
                'RESTRICT',
                'CASCADE'
            );
        }

        $scheduleLink = '{{%integration_schedule_link}}';
        if ($this->db->schema->getTableSchema($scheduleLink, true) === null) {
            $this->createTable($scheduleLink, [
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
            ]);
            $this->createIndex(
                'ux_integration_schedule_link',
                $scheduleLink,
                ['source_system', 'external_schedule_id'],
                true
            );
            $this->createIndex('idx_isl_pes', $scheduleLink, 'id_profesional_efector_servicio');
            $this->addForeignKey(
                'fk_isl_pes',
                $scheduleLink,
                'id_profesional_efector_servicio',
                '{{%profesional_efector_servicio}}',
                'id',
                'RESTRICT',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $scheduleLink = '{{%integration_schedule_link}}';
        if ($this->db->schema->getTableSchema($scheduleLink, true) !== null) {
            $this->dropForeignKey('fk_isl_pes', $scheduleLink);
            $this->dropTable($scheduleLink);
        }

        $serviceCode = '{{%integration_fhir_service_code}}';
        if ($this->db->schema->getTableSchema($serviceCode, true) !== null) {
            $this->dropForeignKey('fk_ifsc_servicio', $serviceCode);
            $this->dropTable($serviceCode);
        }

        $personas = '{{%personas}}';
        $schema = $this->db->schema->getTableSchema($personas, true);
        if ($schema !== null && isset($schema->columns['cuil'])) {
            $this->dropIndex('ux_personas_cuil', $personas);
            $this->dropColumn($personas, 'cuil');
        }
    }
}
