<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\HistoryExchange\ClinicalHistoryOutboundEnqueueService;
use common\components\Domain\Integrations\ClinicalHistory\ClinicalHistoryExchangeRegistry;
use common\components\Domain\Integrations\ClinicalHistory\Mapper\FhirClinicalHistoryBundleMapper;
use common\models\Clinical\Encounter;

class ClinicalHistoryOutboundEnqueueServiceTest extends \Codeception\Test\Unit
{
    protected function _before(): void
    {
        \Yii::$app->params['clinicalHistoryExchange'] = [
            'enabled' => true,
            'default' => 'null',
            'exchange_profile' => 'encounter-document-v1',
            'encounter_classes' => ['AMB', 'EMER', 'IMP'],
            'retry' => ['delay_after_finalize_seconds' => 0],
            'connectors' => [
                'null' => [
                    'class' => \common\components\Domain\Integrations\ClinicalHistory\Connector\NullClinicalHistoryExchangeConnector::class,
                ],
            ],
        ];
    }

    public function testMapperProduceBundleDocument(): void
    {
        $encounter = new Encounter([
            'id' => 99,
            'subject_persona_id' => 1,
            'encounter_class' => 'AMB',
            'status' => EncounterStatus::FINISHED,
            'period_start' => '2026-06-18 10:00:00',
            'period_end' => '2026-06-18 10:30:00',
            'note' => 'Paciente estable.',
            'reason_text' => 'Control',
        ]);

        $bundle = (new FhirClinicalHistoryBundleMapper())->buildForEncounter($encounter);
        verify($bundle['resourceType'])->equals('Bundle');
        verify($bundle['type'])->equals('document');
        verify($bundle['entry'])->notEmpty();

        $types = array_map(
            static fn (array $e) => $e['resource']['resourceType'] ?? '',
            $bundle['entry']
        );
        verify($types)->contains('Composition');
        verify($types)->contains('Encounter');
        verify($types)->contains('Patient');
    }

    public function testNoEncolaSiMasterDisabled(): void
    {
        \Yii::$app->params['clinicalHistoryExchange']['enabled'] = false;
        $encounter = new Encounter([
            'subject_persona_id' => 1,
            'encounter_class' => 'AMB',
            'status' => EncounterStatus::FINISHED,
        ]);

        $job = (new ClinicalHistoryOutboundEnqueueService())->scheduleIfApplicable($encounter);
        verify($job)->null();
    }

    public function testNoEncolaSiEncounterNoFinalizado(): void
    {
        $encounter = new Encounter([
            'subject_persona_id' => 1,
            'encounter_class' => 'AMB',
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $job = (new ClinicalHistoryOutboundEnqueueService())->scheduleIfApplicable($encounter);
        verify($job)->null();
    }

    public function testRegistryMasterEnabled(): void
    {
        verify(ClinicalHistoryExchangeRegistry::isMasterEnabled())->true();
    }
}
