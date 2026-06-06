<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Scheduling\Service\ReservaTriageServicioMapService;
use common\components\Scheduling\Service\ReservaTriageServicioSugeridoService;

class ReservaTriageServicioSugeridoServiceTest extends Unit
{
    public function testResolverRolHubCuandoHayTriage(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $rol = $svc->resolverRolDesdeDraft([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_piel',
            'triage_detalle' => 'det_piel_erupcion',
        ], true);
        $this->assertSame('medicina_clinica', $rol);
    }

    public function testControlCronicoUsaHub(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $rol = $svc->resolverRolDesdeDraft([
            'triage_raiz' => 'control_cronico',
        ], true);
        $this->assertSame('medicina_clinica', $rol);
    }

    public function testFiltrarItemsUiJsonSinBdListaVacia(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $items = [
            ['id' => '10', 'name' => 'Medicina Clínica'],
            ['id' => '20', 'name' => 'Oftalmología'],
        ];

        $filtered = $svc->filtrarItemsUiJson($items, [
            'triage_raiz' => 'sintoma_nuevo',
            'triage_detalle' => 'det_cabeza_dolor',
        ], true);

        $this->assertSame([], $filtered);
    }

    public function testFiltrarItemsConIdsResueltos(): void
    {
        $svc = new class extends ReservaTriageServicioSugeridoService {
            public function resolverParaDraft(array $draft, bool $soloHubPaciente = false): array
            {
                return [
                    'rol' => 'medicina_clinica',
                    'rol_label' => 'Medicina clínica',
                    'id_servicios' => [10],
                    'filtrado_aplicado' => true,
                ];
            }
        };

        $items = [
            ['id' => '10', 'name' => 'Medicina Clínica'],
            ['id' => '20', 'name' => 'Oftalmología'],
        ];
        $filtered = $svc->filtrarItemsUiJson($items, [
            'triage_raiz' => 'sintoma_nuevo',
        ], true);
        $this->assertCount(1, $filtered);
        $this->assertSame('10', $filtered[0]['id']);
    }

    public function testMapHubRol(): void
    {
        $map = new ReservaTriageServicioMapService();
        $this->assertSame('medicina_clinica', $map->getHubRol());
        $this->assertTrue($map->isHubRol('medicina_clinica'));
        $this->assertFalse($map->permiteAutogestionPaciente('oftalmologia'));
        $this->assertTrue($map->teleconsultaSoloConDerivacion('oftalmologia'));
    }

    public function testMapTramiteAdminHeredaMedicinaClinica(): void
    {
        $map = new ReservaTriageServicioMapService();
        $criteria = $map->getMatchCriteriaForRol('tramite_admin');
        $this->assertNotNull($criteria);
        $this->assertContains('medicina clínica', $criteria['nombre_patterns']);
        $this->assertContains('Medico', $criteria['item_names']);
    }
}
