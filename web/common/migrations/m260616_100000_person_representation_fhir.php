<?php

use yii\db\Migration;

/**
 * Representación operativa paciente (FHIR RelatedPerson + Consent).
 *
 * @see web/docs/plans/representacion-paciente-fhir/
 */
class m260616_100000_person_representation_fhir extends Migration
{
    public function safeUp()
    {
        $relationshipType = '{{%relationship_type}}';
        if ($this->db->schema->getTableSchema($relationshipType, true) === null) {
            $this->createTable($relationshipType, [
                'id' => $this->primaryKey(),
                'code' => $this->string(32)->notNull(),
                'label' => $this->string(128)->notNull(),
                'hl7_code' => $this->string(64)->null(),
                'regime_allowed' => $this->string(16)->notNull()->defaultValue('both'),
                'requires_legal_document' => $this->boolean()->notNull()->defaultValue(false),
                'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
                'active' => $this->boolean()->notNull()->defaultValue(true),
            ]);
            $this->createIndex('uidx_relationship_type_code', $relationshipType, 'code', true);
        }

        $personRelated = '{{%person_related}}';
        if ($this->db->schema->getTableSchema($personRelated, true) === null) {
            $this->createTable($personRelated, [
                'id' => $this->primaryKey(),
                'subject_persona_id' => $this->integer()->notNull(),
                'actor_persona_id' => $this->integer()->notNull(),
                'relationship_type_id' => $this->integer()->notNull(),
                'regime' => $this->string(32)->notNull(),
                'status' => $this->string(16)->notNull()->defaultValue('pending'),
                'verified_by' => $this->string(16)->notNull()->defaultValue('none'),
                'verified_at' => $this->dateTime()->null(),
                'blocked_reason' => $this->string(32)->null(),
                'blocked_at' => $this->dateTime()->null(),
                'blocked_by_user_id' => $this->integer()->null(),
                'permissions_json' => $this->text()->null(),
                'evidence_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_person_related_subject_status', $personRelated, ['subject_persona_id', 'status']);
            $this->createIndex('ix_person_related_actor_status', $personRelated, ['actor_persona_id', 'status']);
            $this->createIndex(
                'uidx_person_related_subject_actor_regime',
                $personRelated,
                ['subject_persona_id', 'actor_persona_id', 'regime'],
                true
            );
            $this->addForeignKey(
                'fk_person_related_subject',
                $personRelated,
                'subject_persona_id',
                '{{%personas}}',
                'id_persona',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_person_related_actor',
                $personRelated,
                'actor_persona_id',
                '{{%personas}}',
                'id_persona',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_person_related_relationship_type',
                $personRelated,
                'relationship_type_id',
                $relationshipType,
                'id',
                'RESTRICT',
                'CASCADE'
            );
        }

        $consent = '{{%person_delegation_consent}}';
        if ($this->db->schema->getTableSchema($consent, true) === null) {
            $this->createTable($consent, [
                'id' => $this->primaryKey(),
                'person_related_id' => $this->integer()->notNull(),
                'status' => $this->string(16)->notNull()->defaultValue('active'),
                'granted_at' => $this->dateTime()->notNull(),
                'revoked_at' => $this->dateTime()->null(),
                'provision_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_person_delegation_consent_related', $consent, ['person_related_id', 'status']);
            $this->addForeignKey(
                'fk_person_delegation_consent_related',
                $consent,
                'person_related_id',
                $personRelated,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $audit = '{{%person_related_audit_log}}';
        if ($this->db->schema->getTableSchema($audit, true) === null) {
            $this->createTable($audit, [
                'id' => $this->primaryKey(),
                'person_related_id' => $this->integer()->null(),
                'actor_persona_id' => $this->integer()->notNull(),
                'subject_persona_id' => $this->integer()->notNull(),
                'action' => $this->string(64)->notNull(),
                'id_user' => $this->integer()->null(),
                'payload_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ix_person_related_audit_subject', $audit, ['subject_persona_id', 'created_at']);
            $this->createIndex('ix_person_related_audit_actor', $audit, ['actor_persona_id', 'created_at']);
        }

        $this->seedRelationshipTypes();
    }

    public function safeDown()
    {
        $audit = '{{%person_related_audit_log}}';
        if ($this->db->schema->getTableSchema($audit, true) !== null) {
            $this->dropTable($audit);
        }

        $consent = '{{%person_delegation_consent}}';
        if ($this->db->schema->getTableSchema($consent, true) !== null) {
            $this->dropForeignKey('fk_person_delegation_consent_related', $consent);
            $this->dropTable($consent);
        }

        $personRelated = '{{%person_related}}';
        if ($this->db->schema->getTableSchema($personRelated, true) !== null) {
            $this->dropForeignKey('fk_person_related_relationship_type', $personRelated);
            $this->dropForeignKey('fk_person_related_actor', $personRelated);
            $this->dropForeignKey('fk_person_related_subject', $personRelated);
            $this->dropTable($personRelated);
        }

        $relationshipType = '{{%relationship_type}}';
        if ($this->db->schema->getTableSchema($relationshipType, true) !== null) {
            $this->dropTable($relationshipType);
        }
    }

    private function seedRelationshipTypes(): void
    {
        $table = '{{%relationship_type}}';
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        $rows = [
            ['padre', 'Padre', 'FTH', 'A', false, 10],
            ['madre', 'Madre', 'MTH', 'A', false, 20],
            ['tutor_legal', 'Tutor legal', null, 'A', true, 30],
            ['conyuge', 'Cónyuge', 'SPS', 'B', false, 40],
            ['hijo', 'Hijo/a', 'CHILD', 'B', false, 50],
            ['hermano', 'Hermano/a', 'SIB', 'B', false, 60],
            ['otro', 'Otro', null, 'B', false, 99],
        ];

        foreach ($rows as [$code, $label, $hl7, $regimeAllowed, $requiresDoc, $sort]) {
            $exists = (new \yii\db\Query())
                ->from($table)
                ->where(['code' => $code])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->insert($table, [
                'code' => $code,
                'label' => $label,
                'hl7_code' => $hl7,
                'regime_allowed' => $regimeAllowed,
                'requires_legal_document' => $requiresDoc ? 1 : 0,
                'sort_order' => $sort,
                'active' => 1,
            ]);
        }
    }
}
