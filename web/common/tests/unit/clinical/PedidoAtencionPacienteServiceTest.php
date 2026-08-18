<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Access\CodingSystems;
use common\components\Domain\Clinical\Access\InMemoryLineaActoCatalog;
use common\components\Domain\Clinical\Access\PedidoAtencion;
use common\components\Domain\Clinical\Access\PedidoAtencionMetadata;
use common\components\Domain\Clinical\Access\PedidoAtencionPacienteService;
use common\components\Domain\Clinical\Access\PedidoAtencionService;
use common\components\Domain\Scheduling\Service\ReservaTriageServicioSugeridoService;

class PedidoAtencionPacienteServiceTest extends Unit
{
    protected function _before(): void
    {
        PedidoAtencionMetadata::resetCacheForTests();
    }

    public function testParseActoValue(): void
    {
        $parsed = PedidoAtencionPacienteService::parseActoValue(
            CodingSystems::SNOMED . '|16310003'
        );
        $this->assertNotNull($parsed);
        $this->assertSame('16310003', $parsed['code']);
        $this->assertSame(CodingSystems::SNOMED, $parsed['system']);
        $this->assertNull(PedidoAtencionPacienteService::parseActoValue('local|x'));
    }

    public function testAplicarFlagsResuelveLineaUnica(): void
    {
        $catalog = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
            [
                [
                    'linea_id' => 11,
                    'linea_label' => 'RADIOLOGIA',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => true,
                ],
            ]
        );
        $svc = new PedidoAtencionPacienteService(new PedidoAtencionService($catalog), $catalog);
        $draft = [
            'triage_raiz' => PedidoAtencionPacienteService::TRIAGE_RAIZ_ESTUDIO,
            'pedido_acto' => CodingSystems::SNOMED . '|16310003',
        ];
        $svc->aplicarFlagsEnDraft($draft);

        $this->assertSame('11', (string) $draft['id_servicio_asignado']);
        $this->assertSame('1', $draft[PedidoAtencionPacienteService::DRAFT_SERVICIO_RESUELTO]);
        $this->assertSame('11', $draft[PedidoAtencionPacienteService::DRAFT_LINEA_IDS]);
        $this->assertSame(PedidoAtencion::MODO_ESTUDIO, $draft[PedidoAtencionPacienteService::DRAFT_MODO]);
    }

    public function testAplicarFlagsVariasLineasNoAsignaServicio(): void
    {
        $catalog = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
            [
                [
                    'linea_id' => 39,
                    'linea_label' => 'IMAGENES',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                ],
                [
                    'linea_id' => 11,
                    'linea_label' => 'RADIOLOGIA',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                ],
            ]
        );
        $svc = new PedidoAtencionPacienteService(new PedidoAtencionService($catalog), $catalog);
        $draft = [
            'triage_raiz' => PedidoAtencionPacienteService::TRIAGE_RAIZ_ESTUDIO,
            'pedido_acto' => CodingSystems::SNOMED . '|16310003',
        ];
        $svc->aplicarFlagsEnDraft($draft);

        $this->assertSame('0', $draft[PedidoAtencionPacienteService::DRAFT_SERVICIO_RESUELTO]);
        $this->assertArrayNotHasKey('id_servicio_asignado', $draft);
        $ids = $svc->lineaIdsDesdeDraft($draft);
        $this->assertCount(2, $ids);
    }

    public function testSugeridoFiltraPorPedidoLineas(): void
    {
        $catalog = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'US',
                ],
            ],
            [
                [
                    'linea_id' => 11,
                    'linea_label' => 'RADIOLOGIA',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => true,
                ],
            ]
        );
        // ReservaTriageServicioSugeridoService usa PedidoAtencionPacienteService con DB por defecto;
        // validamos el contrato de draft + filtrarItemsPorIds vía resolverParaDraft con draft ya resuelto.
        $draft = [
            'triage_raiz' => PedidoAtencionPacienteService::TRIAGE_RAIZ_ESTUDIO,
            'pedido_acto' => CodingSystems::SNOMED . '|16310003',
            'pedido_linea_ids' => '11',
            'pedido_modo' => PedidoAtencion::MODO_ESTUDIO,
        ];
        // Sin DB de actos: al resolver de nuevo puede vaciar; set flags como haría el paciente svc.
        $paciente = new PedidoAtencionPacienteService(new PedidoAtencionService($catalog), $catalog);
        $paciente->aplicarFlagsEnDraft($draft);

        $sugerido = new ReservaTriageServicioSugeridoService();
        $items = [
            ['id' => '11', 'name' => 'RADIOLOGIA'],
            ['id' => '7', 'name' => 'MED CLINICA'],
        ];
        $filtered = $sugerido->filtrarItemsUiJson($items, $draft, false);
        $this->assertCount(1, $filtered);
        $this->assertSame('11', $filtered[0]['id']);
    }

    public function testOpcionesActoDesdeCatalogoMemoria(): void
    {
        $catalog = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
            []
        );
        $svc = new PedidoAtencionPacienteService(new PedidoAtencionService($catalog), $catalog);
        $opts = $svc->opcionesActoParaTriagePaso();
        $this->assertCount(1, $opts);
        $this->assertSame(CodingSystems::SNOMED . '|16310003', $opts[0]['code']);
        $this->assertSame('Ecografía', $opts[0]['label']);
    }

    public function testHidratarDesdeMensajeEcografia(): void
    {
        $catalog = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
                [
                    'code' => '71651007',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Mammography',
                ],
            ],
            [
                [
                    'linea_id' => 11,
                    'linea_label' => 'RADIOLOGIA',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => true,
                ],
            ]
        );
        $svc = new PedidoAtencionPacienteService(new PedidoAtencionService($catalog), $catalog);
        $draft = [];
        $svc->hidratarDesdeMensaje($draft, 'Necesito una ecografía');

        $this->assertSame(PedidoAtencionPacienteService::TRIAGE_RAIZ_ESTUDIO, $draft['triage_raiz']);
        $this->assertSame(CodingSystems::SNOMED . '|16310003', $draft['pedido_acto']);
        $this->assertSame('11', (string) $draft['id_servicio_asignado']);
        $this->assertSame(PedidoAtencion::MODO_ESTUDIO, $draft[PedidoAtencionPacienteService::DRAFT_MODO]);
    }
}
