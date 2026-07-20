<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\CareProtocolCatalogService;
use common\components\Domain\Scheduling\Service\ControlSeguimientoHubService;
use common\models\Clinical\CareProtocol;

class ControlSeguimientoHubServiceTest extends Unit
{
    protected function _before(): void
    {
        ControlSeguimientoHubService::resetCacheForTests();
        CareProtocolCatalogService::resetCacheForTests();
        CareProtocolCatalogService::setOverrideForTests($this->fixtureProtocols());
    }

    protected function _after(): void
    {
        CareProtocolCatalogService::resetCacheForTests();
    }

    public function testHubSoloDominioSinExtrasNiTurno(): void
    {
        $svc = new ControlSeguimientoHubService();
        $items = $svc->listHubItems(0);
        $ids = array_column($items, 'id');
        $this->assertNotContains(ControlSeguimientoHubService::ANCHOR_GENERAL, $ids);
        $this->assertNotContains(ControlSeguimientoHubService::ANCHOR_CONSULTA_GENERAL, $ids);
        $this->assertNotContains(ControlSeguimientoHubService::ANCHOR_CONSULTA_PREVIA, $ids);
        $this->assertSame([], $items);
    }

    public function testApplyAnchorCarePlan(): void
    {
        $svc = new ControlSeguimientoHubService();
        $draft = ['control_hub_anchor' => 'cp:42'];
        $svc->applyAnchorToDraft($draft);
        $this->assertSame('42', $draft['care_plan_id'] ?? null);
        $this->assertSame('seguimiento', $draft['intake_tipo'] ?? null);
        $this->assertSame('care_plan', $draft['control_hub_kind'] ?? null);
        $this->assertSame('seguimiento_cronico', $draft['triage_raiz'] ?? null);
    }

    public function testApplyAnchorPrefillsFromCarePlanId(): void
    {
        $svc = new ControlSeguimientoHubService();
        $draft = [
            'care_plan_id' => '7',
            'seguimiento_necesidad' => 'renovar_medicacion',
        ];
        $svc->applyAnchorToDraft($draft);
        $this->assertSame('cp:7', $draft['control_hub_anchor'] ?? null);
        $this->assertSame('care_plan', $draft['control_hub_kind'] ?? null);
    }

    public function testApplyAnchorNoSaltaHubConSoloCarePlanId(): void
    {
        $svc = new ControlSeguimientoHubService();
        $draft = ['care_plan_id' => '7'];
        $svc->applyAnchorToDraft($draft);
        $this->assertArrayNotHasKey('control_hub_anchor', $draft);
        $this->assertArrayNotHasKey('control_hub_kind', $draft);
    }

    public function testConditionDefaultActionsDesdeMetadata(): void
    {
        $svc = new ControlSeguimientoHubService();
        $actions = $svc->conditionDefaultActions();
        $codes = array_column($actions, 'code');
        $this->assertContains('consulta_mensaje', $codes);
        $this->assertContains('solicitar_turno', $codes);
    }

    public function testConditionActionsPrefierenProtocoloCuandoHayCodigo(): void
    {
        $svc = new ControlSeguimientoHubService();
        $items = $svc->listConditionActionItems('I10');
        $this->assertNotEmpty($items);
        $this->assertSame('protocol', $items[0]['meta']['source'] ?? null);
        $this->assertSame('hta_control_periodico', $items[0]['meta']['protocol_id'] ?? null);
    }

    public function testResolveConditionActionModalidad(): void
    {
        $svc = new ControlSeguimientoHubService();
        $resolved = $svc->resolveConditionAction('E11', 'solicitar_turno');
        $this->assertNotNull($resolved);
        $this->assertSame('modalidad', $resolved['outcome']);
        $this->assertSame('diabetes_control_periodico', $resolved['protocol_id']);
    }

    public function testApplyAnchorProtocol(): void
    {
        $svc = new ControlSeguimientoHubService();
        $draft = ['control_hub_anchor' => 'prot:control_preventivo_adulto'];
        $svc->applyAnchorToDraft($draft);
        $this->assertSame('control_preventivo_adulto', $draft['protocol_id'] ?? null);
        $this->assertSame('protocol', $draft['control_hub_kind'] ?? null);
        $this->assertSame('seguimiento_cronico', $draft['triage_raiz'] ?? null);
    }

    public function testConditionActionsPorProtocolId(): void
    {
        $svc = new ControlSeguimientoHubService();
        $items = $svc->listConditionActionItems(null, 'control_preventivo_adulto');
        $this->assertNotEmpty($items);
        $this->assertSame('protocol', $items[0]['meta']['source'] ?? null);
        $this->assertSame('control_preventivo_adulto', $items[0]['meta']['protocol_id'] ?? null);
    }

    public function testResolveConditionActionPorProtocolId(): void
    {
        $svc = new ControlSeguimientoHubService();
        $resolved = $svc->resolveConditionAction(null, 'consulta_mensaje', 'vacunas_pediatricas_orientacion');
        $this->assertNotNull($resolved);
        $this->assertSame('captura_mensaje', $resolved['outcome']);
        $this->assertSame('vacunas_pediatricas_orientacion', $resolved['protocol_id']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fixtureProtocols(): array
    {
        $turno = [
            'code' => 'solicitar_turno',
            'label' => 'Pedir turno',
            'description' => '',
            'outcome' => 'modalidad',
            'draft' => ['triage_raiz' => 'seguimiento_cronico'],
        ];
        $msg = [
            'code' => 'consulta_mensaje',
            'label' => 'Consulta por mensaje',
            'description' => '',
            'outcome' => 'captura_mensaje',
            'draft' => ['intake_tipo' => 'consulta_general'],
        ];

        return [
            [
                'id' => 'hta_control_periodico',
                'title' => 'Control de hipertensión',
                'hub_label' => 'Control de hipertensión',
                'enabled' => true,
                'orden' => 10,
                'scope_type' => CareProtocol::SCOPE_NATION,
                'id_provincia' => null,
                'condition_match' => CareProtocol::MATCH_ACTIVE,
                'applies' => [
                    'condition_codes' => ['I10', 'I11'],
                    'age_years' => ['min' => null, 'max' => null],
                    'sex' => [],
                ],
                'actions' => [$turno, $msg],
            ],
            [
                'id' => 'diabetes_control_periodico',
                'title' => 'Control de diabetes',
                'hub_label' => 'Control de diabetes',
                'enabled' => true,
                'orden' => 20,
                'scope_type' => CareProtocol::SCOPE_NATION,
                'id_provincia' => null,
                'condition_match' => CareProtocol::MATCH_ACTIVE,
                'applies' => [
                    'condition_codes' => ['E10', 'E11'],
                    'age_years' => ['min' => null, 'max' => null],
                    'sex' => [],
                ],
                'actions' => [$turno, $msg],
            ],
            [
                'id' => 'control_preventivo_adulto',
                'title' => 'Control de salud adulto',
                'hub_label' => 'Control recomendado (adulto)',
                'enabled' => true,
                'orden' => 40,
                'scope_type' => CareProtocol::SCOPE_NATION,
                'id_provincia' => null,
                'condition_match' => CareProtocol::MATCH_NONE,
                'applies' => [
                    'condition_codes' => [],
                    'age_years' => ['min' => 18, 'max' => 64],
                    'sex' => ['M'],
                ],
                'actions' => [$turno, $msg],
            ],
            [
                'id' => 'vacunas_pediatricas_orientacion',
                'title' => 'Vacunas pediátricas',
                'hub_label' => 'Vacunas',
                'enabled' => true,
                'orden' => 50,
                'scope_type' => CareProtocol::SCOPE_NATION,
                'id_provincia' => null,
                'condition_match' => CareProtocol::MATCH_NONE,
                'applies' => [
                    'condition_codes' => [],
                    'age_years' => ['min' => 0, 'max' => 17],
                    'sex' => [],
                ],
                'actions' => [$msg],
            ],
        ];
    }
}
