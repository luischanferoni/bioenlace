<?php

use yii\db\Migration;

/**
 * Explicita el sistema de codificación SNOMED CT en las actividades del plan.
 */
class m260717_100000_add_medication_request_code_system extends Migration
{
    private const SNOMED_URI = 'http://snomed.info/sct';

    public function safeUp()
    {
        $medicationRequest = '{{%medication_request}}';
        $medicationSchema = $this->db->schema->getTableSchema($medicationRequest, true);
        if ($medicationSchema !== null) {
            if (!isset($medicationSchema->columns['medication_code_system'])) {
                $this->addColumn(
                    $medicationRequest,
                    'medication_code_system',
                    $this->string(128)->null()->after('medication_code')
                );
            }
            $this->update(
                $medicationRequest,
                ['medication_code_system' => self::SNOMED_URI],
                [
                    'and',
                    ['not', ['medication_code' => null]],
                    ['<>', 'medication_code', ''],
                    ['medication_code_system' => null],
                ]
            );
        }

        $serviceRequest = '{{%service_request}}';
        $serviceSchema = $this->db->schema->getTableSchema($serviceRequest, true);
        if ($serviceSchema !== null && isset($serviceSchema->columns['code_system'])) {
            $this->update(
                $serviceRequest,
                ['code_system' => self::SNOMED_URI],
                ['and', ['not', ['code' => null]], ['<>', 'code', ''], ['code_system' => null]]
            );
        }
    }

    public function safeDown()
    {
        $medicationRequest = '{{%medication_request}}';
        $schema = $this->db->schema->getTableSchema($medicationRequest, true);
        if ($schema !== null && isset($schema->columns['medication_code_system'])) {
            $this->dropColumn($medicationRequest, 'medication_code_system');
        }
    }
}
