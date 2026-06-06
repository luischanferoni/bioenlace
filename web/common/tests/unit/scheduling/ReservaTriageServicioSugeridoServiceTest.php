<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Scheduling\Service\ReservaTriageServicioMapService;
use common\components\Scheduling\Service\ReservaTriageServicioRol;
use common\components\Scheduling\Service\ReservaTriageServicioRolResolver;
use common\components\Scheduling\Service\ReservaTriageServicioSugeridoService;

class ReservaTriageServicioSugeridoServiceTest extends Unit
{
    public function testRolIdealDermatologiaDesdePiel(): void
    {
        $resolver = new ReservaTriageServicioRolResolver();
        $res = $resolver->resolveDesdeDraft([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_piel',
            'triage_detalle' => 'det_piel_erupcion',
        ]);
        $this->assertSame(ReservaTriageServicioRol::DERMATOLOGIA, $res->rol_ideal);
        $this->assertSame('det_piel_erupcion', $res->triage_codigo_resolutor);
        $this->assertFalse($res->autogestion_disponible);
        $this->assertSame([], $res->id_servicios_reservables);
        $this->assertNotNull($res->mensaje_orientacion);
    }

    public function testRolIdealTraumatologiaDesdeEspalda(): void
    {
        $resolver = new ReservaTriageServicioRolResolver();
        $res = $resolver->resolveDesdeDraft([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_espalda',
            'triage_detalle' => 'det_espalda_dolor',
        ]);
        $this->assertSame(ReservaTriageServicioRol::TRAUMATOLOGIA, $res->rol_ideal);
        $this->assertFalse($res->autogestion_disponible);
    }

    public function testControlCronicoUsaMedicinaClinica(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $rol = $svc->resolverRolDesdeDraft([
            'triage_raiz' => 'control_cronico',
        ], false);
        $this->assertSame(ReservaTriageServicioRol::MEDICINA_CLINICA, $rol);
    }

    public function testFiltrarItemsUiJsonSinBdListaVaciaParaDermatologia(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $items = [
            ['id' => '8', 'name' => 'MED CLINICA'],
            ['id' => '23', 'name' => 'OFTALMOLOGIA'],
            ['id' => '66', 'name' => 'DERMATOLOGÍA'],
        ];

        $filtered = $svc->filtrarItemsUiJson($items, [
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_piel',
            'triage_detalle' => 'det_piel_erupcion',
        ], false);

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
                    'autogestion_disponible' => true,
                    'triage_codigo_resolutor' => 'det_general_otro',
                    'mensaje_orientacion' => null,
                    'mensaje_lista' => 'Elegí servicio',
                ];
            }
        };

        $items = [
            ['id' => '10', 'name' => 'MED CLINICA'],
            ['id' => '20', 'name' => 'OFTALMOLOGIA'],
        ];
        $filtered = $svc->filtrarItemsUiJson($items, [
            'triage_raiz' => 'sintoma_nuevo',
        ], false);
        $this->assertCount(1, $filtered);
        $this->assertSame('10', $filtered[0]['id']);
    }

    public function testAplicarFlagsNoPreseleccionaServicio(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $draft = [
            'triage_raiz' => 'control_cronico',
            'triage_alarmas' => 'alarma_ninguna',
        ];
        $svc->aplicarFlagsEnDraft($draft);
        $this->assertArrayNotHasKey('id_servicio_sugerido', $draft);
        $this->assertSame('medicina_clinica', $draft['triage_servicio_rol_ideal'] ?? '');
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
        $this->assertContains('med clinica', $criteria['nombre_patterns']);
        $this->assertContains('Medico', $criteria['item_names']);
    }

    public function testBuiltinCodigoRolPiel(): void
    {
        $this->assertSame(
            ReservaTriageServicioRol::DERMATOLOGIA,
            ReservaTriageServicioRol::rolBuiltinParaCodigo('det_piel_erupcion')
        );
    }
}
