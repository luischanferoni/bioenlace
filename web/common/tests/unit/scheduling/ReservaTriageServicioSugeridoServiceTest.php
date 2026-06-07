<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Scheduling\Service\ReservaTriageAccesoConfig;
use common\components\Scheduling\Service\ReservaTriageServicioRolResolver;
use common\components\Scheduling\Service\ReservaTriageServicioSugeridoService;

class ReservaTriageServicioSugeridoServiceTest extends Unit
{
    public function testEspecialistaSinAutogestionListaVacia(): void
    {
        $resolver = new ReservaTriageServicioRolResolver();
        $res = $resolver->resolveDesdeDraft([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_piel',
        ]);
        $this->assertSame('zona_piel', $res->triage_codigo_resolutor);
        $this->assertFalse($res->autogestion_disponible);
        $this->assertSame([], $res->id_servicios_reservables);
        $this->assertNotNull($res->mensaje_orientacion);
    }

    public function testTraumatologiaDesdeEspaldaSinAutogestionDirecta(): void
    {
        $resolver = new ReservaTriageServicioRolResolver();
        $res = $resolver->resolveDesdeDraft([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_espalda',
        ]);
        $this->assertFalse($res->autogestion_disponible);
    }

    public function testControlCronicoResuelveServicios(): void
    {
        $svc = new ReservaTriageServicioSugeridoService();
        $res = $svc->resolverParaDraft([
            'triage_raiz' => 'seguimiento_cronico',
        ], false);
        $this->assertNotSame('', $res['rol_label']);
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
        ], false);

        $this->assertSame([], $filtered);
    }

    public function testPriorizarItemsPresencialMantieneTodos(): void
    {
        $svc = new class extends ReservaTriageServicioSugeridoService {
            public function resolverParaDraft(array $draft, bool $soloHubPaciente = false): array
            {
                return parent::resolverParaDraft($draft, $soloHubPaciente);
            }
        };
        $items = [
            ['id' => '8', 'name' => 'MED CLINICA'],
            ['id' => '66', 'name' => 'DERMATOLOGÍA'],
        ];
        $out = $svc->priorizarItemsSegunTriage($items, [
            'triage_raiz' => 'sintoma_nuevo',
            'triage_zona' => 'zona_piel',
        ]);
        $this->assertCount(2, $out);
    }

    public function testEsListadoPresencial(): void
    {
        $this->assertTrue(ReservaTriageServicioSugeridoService::esListadoPresencial(['tipo_atencion' => 'presencial']));
        $this->assertFalse(ReservaTriageServicioSugeridoService::esListadoPresencial(['tipo_atencion' => 'teleconsulta']));
    }

    public function testFiltrarItemsConIdsResueltos(): void
    {
        $svc = new class extends ReservaTriageServicioSugeridoService {
            public function resolverParaDraft(array $draft, bool $soloHubPaciente = false): array
            {
                return [
                    'rol' => '10',
                    'rol_label' => 'MED CLINICA',
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
            'triage_raiz' => 'seguimiento_cronico',
            'triage_alarmas' => 'alarma_ninguna',
        ];
        $svc->aplicarFlagsEnDraft($draft);
        $this->assertArrayNotHasKey('id_servicio_sugerido', $draft);
        $this->assertArrayHasKey('triage_servicio_rol_ideal', $draft);
    }

    public function testAccesoConfigEspecialistaConDerivacion(): void
    {
        $this->assertTrue(ReservaTriageAccesoConfig::especialistaSoloTeleconsultaConDerivacion());
    }
}
