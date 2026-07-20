<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\CareProtocolAdminService;
use common\components\Domain\Clinical\Service\CareProtocolCatalogService;
use common\components\Domain\Clinical\Service\CareProtocolMatcherService;
use common\models\Clinical\CareProtocol;

class CareProtocolMatcherServiceTest extends Unit
{
    protected function _before(): void
    {
        CareProtocolCatalogService::resetCacheForTests();
        CareProtocolCatalogService::setOverrideForTests($this->fixtureProtocols());
    }

    protected function _after(): void
    {
        CareProtocolCatalogService::resetCacheForTests();
    }

    public function testCatalogTieneProtocolos(): void
    {
        $svc = new CareProtocolCatalogService();
        $all = $svc->allProtocols();
        $this->assertNotEmpty($all);
        $ids = array_column($all, 'id');
        $this->assertContains('hta_control_periodico', $ids);
        $this->assertContains('diabetes_control_periodico', $ids);
        $this->assertNotContains('vacunas_pediatricas_orientacion', $ids);
    }

    public function testCatalogFiltraProvincia(): void
    {
        $svc = new CareProtocolCatalogService();
        $nationOnly = array_column($svc->allProtocols(null), 'id');
        $this->assertContains('hta_control_periodico', $nationOnly);
        $this->assertNotContains('vacunas_cba', $nationOnly);

        $withProv = array_column($svc->allProtocols(14), 'id');
        $this->assertContains('hta_control_periodico', $withProv);
        $this->assertContains('vacunas_cba', $withProv);

        $otherProv = array_column($svc->allProtocols(99), 'id');
        $this->assertNotContains('vacunas_cba', $otherProv);
    }

    public function testMatchI10Exacto(): void
    {
        $m = new CareProtocolMatcherService();
        $p = $m->matchByConditionCode('I10');
        $this->assertNotNull($p);
        $this->assertSame('hta_control_periodico', $p['id']);
    }

    public function testMatchE11ConSubcodigo(): void
    {
        $m = new CareProtocolMatcherService();
        $p = $m->matchByConditionCode('E11.9');
        $this->assertNotNull($p);
        $this->assertSame('diabetes_control_periodico', $p['id']);
    }

    public function testSinMatchDevuelveNull(): void
    {
        $m = new CareProtocolMatcherService();
        $this->assertNull($m->matchByConditionCode('Z99.9'));
    }

    public function testConditionMatchChronicRequiereMarcador(): void
    {
        $m = new CareProtocolMatcherService();
        $this->assertNull($m->matchByConditionCode('J45', null, [
            'clinical_status' => 'ACTIVE',
            'note' => null,
        ]));
        $p = $m->matchByConditionCode('J45', null, [
            'clinical_status' => 'ACTIVE',
            'note' => '{"cronico":"SI"}',
        ]);
        $this->assertNotNull($p);
        $this->assertSame('asma_cronica_strict', $p['id']);
    }

    public function testActionsIncluyenOutcomeYDraft(): void
    {
        $m = new CareProtocolMatcherService();
        $actions = $m->actionsForConditionCode('I10');
        $this->assertNotEmpty($actions);
        $codes = array_column($actions, 'code');
        $this->assertContains('solicitar_turno', $codes);
        $turno = null;
        foreach ($actions as $a) {
            if ($a['code'] === 'solicitar_turno') {
                $turno = $a;
                break;
            }
        }
        $this->assertNotNull($turno);
        $this->assertSame('modalidad', $turno['outcome']);
        $this->assertSame('hta_control_periodico', $turno['protocol_id']);
    }

    public function testMatchByProfileAdulto(): void
    {
        $m = new CareProtocolMatcherService();
        $matched = $m->matchByProfile(45, 'M');
        $ids = array_column($matched, 'id');
        $this->assertContains('control_preventivo_adulto', $ids);
        $this->assertNotContains('control_ginecologico_edad', $ids);
        $this->assertNotContains('vacunas_pediatricas_orientacion', $ids);
    }

    public function testMatchByProfileGinecologico(): void
    {
        $m = new CareProtocolMatcherService();
        $matched = $m->matchByProfile(30, 'F');
        $ids = array_column($matched, 'id');
        $this->assertContains('control_ginecologico_edad', $ids);
        $this->assertNotContains('control_preventivo_adulto', $ids);
    }

    public function testMatchByProfilePediatricoConJurisdiccion(): void
    {
        $m = new CareProtocolMatcherService();
        $matched = $m->matchByProfile(10, null, 14);
        $ids = array_column($matched, 'id');
        $this->assertContains('vacunas_cba', $ids);
        $this->assertNotContains('control_preventivo_adulto', $ids);

        $sinProv = array_column($m->matchByProfile(10, null, null), 'id');
        $this->assertNotContains('vacunas_cba', $sinProv);
    }

    public function testActionsForProtocolId(): void
    {
        $m = new CareProtocolMatcherService();
        $actions = $m->actionsForProtocolId('control_preventivo_adulto');
        $this->assertNotEmpty($actions);
        $this->assertSame('control_preventivo_adulto', $actions[0]['protocol_id']);
    }

    public function testAdminRechazaNoSuperadmin(): void
    {
        // En unit suite el usuario no es superadmin.
        $this->expectException(\RuntimeException::class);
        (new CareProtocolAdminService())->listar();
    }

    /**
     * Fixture en memoria (sin BD): seed NATION + preventivos de prueba + provincia.
     *
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
                    'condition_codes' => ['I10', 'I11', 'I12', 'I13', 'I15'],
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
                    'condition_codes' => ['E10', 'E11', 'E12', 'E13', 'E14'],
                    'age_years' => ['min' => null, 'max' => null],
                    'sex' => [],
                ],
                'actions' => [$turno, $msg],
            ],
            [
                'id' => 'asma_cronica_strict',
                'title' => 'Control de asma crónica',
                'hub_label' => 'Control de asma',
                'enabled' => true,
                'orden' => 30,
                'scope_type' => CareProtocol::SCOPE_NATION,
                'id_provincia' => null,
                'condition_match' => CareProtocol::MATCH_CHRONIC,
                'applies' => [
                    'condition_codes' => ['J45', 'J46'],
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
                'id' => 'control_ginecologico_edad',
                'title' => 'Control ginecológico',
                'hub_label' => 'Control ginecológico',
                'enabled' => true,
                'orden' => 50,
                'scope_type' => CareProtocol::SCOPE_NATION,
                'id_provincia' => null,
                'condition_match' => CareProtocol::MATCH_NONE,
                'applies' => [
                    'condition_codes' => [],
                    'age_years' => ['min' => 18, 'max' => 64],
                    'sex' => ['F'],
                ],
                'actions' => [$turno, $msg],
            ],
            [
                'id' => 'vacunas_cba',
                'title' => 'Vacunas pediátricas (CBA)',
                'hub_label' => 'Vacunas (Córdoba)',
                'enabled' => true,
                'orden' => 60,
                'scope_type' => CareProtocol::SCOPE_PROVINCE,
                'id_provincia' => 14,
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
