<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\CareProtocolCatalogService;
use common\components\Domain\Clinical\Service\CareProtocolMatcherService;

class CareProtocolMatcherServiceTest extends Unit
{
    protected function _before(): void
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

    public function testActionsIncluyenOutcomeYDraft(): void
    {
        $m = new CareProtocolMatcherService();
        $actions = $m->actionsForConditionCode('J45');
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
        $this->assertSame('asma_control_periodico', $turno['protocol_id']);
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

    public function testMatchByProfilePediatrico(): void
    {
        $m = new CareProtocolMatcherService();
        $matched = $m->matchByProfile(10, null);
        $ids = array_column($matched, 'id');
        $this->assertContains('vacunas_pediatricas_orientacion', $ids);
        $this->assertNotContains('control_preventivo_adulto', $ids);
    }

    public function testActionsForProtocolId(): void
    {
        $m = new CareProtocolMatcherService();
        $actions = $m->actionsForProtocolId('control_preventivo_adulto');
        $this->assertNotEmpty($actions);
        $this->assertSame('control_preventivo_adulto', $actions[0]['protocol_id']);
    }
}
