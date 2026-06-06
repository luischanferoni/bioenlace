<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Scheduling\Service\ReservaTriageServicioMapService;
use common\components\Scheduling\Service\ReservaTriageServicioSugeridoService;

class ReservaTriageServicioSugeridoServiceTest extends Unit
{
    public function testResolverRolDesdeDetallePriorizaSobreZona(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $rol = $svc->resolverRolDesdeDraft([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_piel',
            'triage_detalle' => 'det_cabeza_dolor',
        ]);
        $this->assertSame('clinica_general', $rol);
    }

    public function testResolverRolPielSugiereDermatologia(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $rol = $svc->resolverRolDesdeDraft([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_piel',
            'triage_detalle' => 'det_piel_erupcion',
        ]);
        $this->assertSame('dermatologia', $rol);
    }

    public function testFiltrarItemsUiJsonIntersecaPorId(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $items = [
            ['id' => '10', 'name' => 'Medicina Clínica'],
            ['id' => '20', 'name' => 'Oftalmología'],
        ];

        $filtered = $svc->filtrarItemsUiJson($items, [
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_cabeza_cuello',
            'triage_detalle' => 'det_cabeza_dolor',
        ]);

        // Sin BD: idsServicioParaRol vacío → lista vacía (filtro estricto con triage)
        $this->assertSame([], $filtered);
    }

    public function testFiltrarItemsConIdsResueltos(): void
    {
        $svc = new class extends ReservaTriageServicioSugeridoService {
            public function resolverParaDraft(array $draft): array
            {
                return [
                    'rol' => 'clinica_general',
                    'rol_label' => 'Clínica',
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
            'triage_detalle' => 'det_cabeza_dolor',
        ]);
        $this->assertCount(1, $filtered);
        $this->assertSame('10', $filtered[0]['id']);
    }

    public function testMapTramiteAdminHeredaClinicaGeneral(): void
    {
        $map = new ReservaTriageServicioMapService();
        $criteria = $map->getMatchCriteriaForRol('tramite_admin');
        $this->assertNotNull($criteria);
        $this->assertContains('clinica', $criteria['nombre_patterns']);
        $this->assertContains('Medico', $criteria['item_names']);
    }
}
