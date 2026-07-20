<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Catálogo care_protocol (PlanDefinition-lite) Nación/Provincia + seed HTA/diabetes/asma.
 */
class m260720_160000_care_protocol_catalog extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema('{{%care_protocol}}', true) !== null) {
            echo "    > care_protocol ya existe; omitida creación.\n";
        } else {
            $this->createTable('{{%care_protocol}}', [
                'id' => $this->primaryKey(),
                'protocol_key' => $this->string(64)->notNull(),
                'title' => $this->string(255)->notNull(),
                'hub_label' => $this->string(255)->null(),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'orden' => $this->integer()->notNull()->defaultValue(100),
                'scope_type' => "ENUM('NATION','PROVINCE') NOT NULL DEFAULT 'NATION'",
                'id_provincia' => $this->integer()->null(),
                'age_min' => $this->integer()->null(),
                'age_max' => $this->integer()->null(),
                'sex_json' => $this->text()->null(),
                'condition_codes_json' => $this->text()->null(),
                'condition_match' => "ENUM('none','active','chronic','active_or_chronic') NOT NULL DEFAULT 'none'",
                'actions_json' => $this->text()->notNull(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
            ]);
            $this->createIndex('ux_care_protocol_key', '{{%care_protocol}}', 'protocol_key', true);
            $this->createIndex('ix_care_protocol_scope', '{{%care_protocol}}', ['scope_type', 'id_provincia', 'enabled']);
            if ($this->db->schema->getTableSchema('{{%geo_provincias}}', true) !== null) {
                $this->addForeignKey(
                    'fk_care_protocol_provincia',
                    '{{%care_protocol}}',
                    'id_provincia',
                    '{{%geo_provincias}}',
                    'id_provincia',
                    'RESTRICT',
                    'CASCADE'
                );
            }
        }

        $this->seedNationChronicProtocols();
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema('{{%care_protocol}}', true) !== null) {
            $table = $this->db->schema->getTableSchema('{{%care_protocol}}', true);
            if ($table !== null && isset($table->foreignKeys['fk_care_protocol_provincia'])) {
                $this->dropForeignKey('fk_care_protocol_provincia', '{{%care_protocol}}');
            }
            $this->dropTable('{{%care_protocol}}');
        }
    }

    private function seedNationChronicProtocols(): void
    {
        $table = '{{%care_protocol}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $seeds = [
            [
                'protocol_key' => 'hta_control_periodico',
                'title' => 'Control de hipertensión',
                'hub_label' => 'Control de hipertensión',
                'orden' => 10,
                'condition_codes_json' => json_encode(['I10', 'I11', 'I12', 'I13', 'I15'], JSON_UNESCAPED_UNICODE),
                'condition_match' => 'active',
                'actions_json' => json_encode([
                    [
                        'code' => 'solicitar_turno',
                        'label' => 'Pedir turno de control',
                        'description' => 'Reservá un control de presión / seguimiento.',
                        'outcome' => 'modalidad',
                        'draft' => ['triage_raiz' => 'seguimiento_cronico'],
                    ],
                    [
                        'code' => 'consulta_mensaje',
                        'label' => 'Consulta por mensaje',
                        'description' => 'Escribile al equipo sobre tu hipertensión.',
                        'outcome' => 'captura_mensaje',
                        'draft' => ['intake_tipo' => 'consulta_general'],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'protocol_key' => 'diabetes_control_periodico',
                'title' => 'Control de diabetes',
                'hub_label' => 'Control de diabetes',
                'orden' => 20,
                'condition_codes_json' => json_encode(['E10', 'E11', 'E12', 'E13', 'E14'], JSON_UNESCAPED_UNICODE),
                'condition_match' => 'active',
                'actions_json' => json_encode([
                    [
                        'code' => 'solicitar_turno',
                        'label' => 'Pedir turno de control',
                        'description' => 'Reservá un control de diabetes.',
                        'outcome' => 'modalidad',
                        'draft' => ['triage_raiz' => 'seguimiento_cronico'],
                    ],
                    [
                        'code' => 'consulta_mensaje',
                        'label' => 'Consulta por mensaje',
                        'description' => 'Contá cómo venís con el tratamiento.',
                        'outcome' => 'captura_mensaje',
                        'draft' => ['intake_tipo' => 'consulta_general'],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'protocol_key' => 'asma_control_periodico',
                'title' => 'Control de asma',
                'hub_label' => 'Control de asma',
                'orden' => 30,
                'condition_codes_json' => json_encode(['J45', 'J46'], JSON_UNESCAPED_UNICODE),
                'condition_match' => 'active',
                'actions_json' => json_encode([
                    [
                        'code' => 'solicitar_turno',
                        'label' => 'Pedir turno de control',
                        'description' => '',
                        'outcome' => 'modalidad',
                        'draft' => ['triage_raiz' => 'seguimiento_cronico'],
                    ],
                    [
                        'code' => 'consulta_mensaje',
                        'label' => 'Consulta por mensaje',
                        'description' => '',
                        'outcome' => 'captura_mensaje',
                        'draft' => ['intake_tipo' => 'consulta_general'],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($seeds as $seed) {
            $exists = (new Query())->from($table)->where(['protocol_key' => $seed['protocol_key']])->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->insert($table, [
                'protocol_key' => $seed['protocol_key'],
                'title' => $seed['title'],
                'hub_label' => $seed['hub_label'],
                'enabled' => 1,
                'orden' => $seed['orden'],
                'scope_type' => 'NATION',
                'id_provincia' => null,
                'age_min' => null,
                'age_max' => null,
                'sex_json' => null,
                'condition_codes_json' => $seed['condition_codes_json'],
                'condition_match' => $seed['condition_match'],
                'actions_json' => $seed['actions_json'],
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => null,
                'updated_by' => null,
            ]);
        }
    }
}
