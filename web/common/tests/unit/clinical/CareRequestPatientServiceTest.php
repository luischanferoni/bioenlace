<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CareRequest\Domain\CodingSystems;
use common\components\Domain\Clinical\CareRequest\Domain\InMemoryServiceLineActCatalog;
use common\components\Domain\Clinical\CareRequest\Domain\Model\CareRequest;
use common\components\Domain\Clinical\CareRequest\Application\Service\CareRequestMetadata;
use common\components\Domain\Clinical\CareRequest\Application\UseCase\SubmitPatientCareRequest;
use common\components\Domain\Clinical\CareRequest\Application\UseCase\ResolveCareRequest;
use common\components\Domain\Scheduling\Agenda\Application\Service\ReservaTriageServicioSugeridoService;

class SubmitPatientCareRequestTest extends Unit
{
    protected function _before(): void
    {
        CareRequestMetadata::resetCacheForTests();
    }

    public function testParseActoValue(): void
    {
        $parsed = SubmitPatientCareRequest::parseActoValue(
            CodingSystems::SNOMED . '|16310003'
        );
        $this->assertNotNull($parsed);
        $this->assertSame('16310003', $parsed['code']);
        $this->assertSame(CodingSystems::SNOMED, $parsed['system']);
        $this->assertNull(SubmitPatientCareRequest::parseActoValue('local|x'));
    }

    public function testAplicarFlagsResuelveLineaUnica(): void
    {
        $catalog = new InMemoryServiceLineActCatalog(
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
        $svc = new SubmitPatientCareRequest(new ResolveCareRequest($catalog), $catalog);
        $draft = [
            'triage_raiz' => SubmitPatientCareRequest::TRIAGE_RAIZ_ESTUDIO,
            'pedido_acto' => CodingSystems::SNOMED . '|16310003',
        ];
        $svc->aplicarFlagsEnDraft($draft);

        $this->assertSame('11', (string) $draft['id_servicio_asignado']);
        $this->assertSame('1', $draft[SubmitPatientCareRequest::DRAFT_SERVICIO_RESUELTO]);
        $this->assertSame('11', $draft[SubmitPatientCareRequest::DRAFT_LINEA_IDS]);
        $this->assertSame(CareRequest::MODO_ESTUDIO, $draft[SubmitPatientCareRequest::DRAFT_MODO]);
    }

    public function testAplicarFlagsVariasLineasNoAsignaServicio(): void
    {
        $catalog = new InMemoryServiceLineActCatalog(
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
        $svc = new SubmitPatientCareRequest(new ResolveCareRequest($catalog), $catalog);
        $draft = [
            'triage_raiz' => SubmitPatientCareRequest::TRIAGE_RAIZ_ESTUDIO,
            'pedido_acto' => CodingSystems::SNOMED . '|16310003',
        ];
        $svc->aplicarFlagsEnDraft($draft);

        $this->assertSame('0', $draft[SubmitPatientCareRequest::DRAFT_SERVICIO_RESUELTO]);
        $this->assertArrayNotHasKey('id_servicio_asignado', $draft);
        $ids = $svc->lineaIdsDesdeDraft($draft);
        $this->assertCount(2, $ids);
    }

    public function testSugeridoFiltraPorPedidoLineas(): void
    {
        $catalog = new InMemoryServiceLineActCatalog(
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
        // ReservaTriageServicioSugeridoService usa SubmitPatientCareRequest con DB por defecto;
        // validamos el contrato de draft + filtrarItemsPorIds vÃ­a resolverParaDraft con draft ya resuelto.
        $draft = [
            'triage_raiz' => SubmitPatientCareRequest::TRIAGE_RAIZ_ESTUDIO,
            'pedido_acto' => CodingSystems::SNOMED . '|16310003',
            'pedido_linea_ids' => '11',
            'pedido_modo' => CareRequest::MODO_ESTUDIO,
        ];
        // Sin DB de actos: al resolver de nuevo puede vaciar; set flags como harÃ­a el paciente svc.
        $paciente = new SubmitPatientCareRequest(new ResolveCareRequest($catalog), $catalog);
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
        $catalog = new InMemoryServiceLineActCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
            []
        );
        $svc = new SubmitPatientCareRequest(new ResolveCareRequest($catalog), $catalog);
        $opts = $svc->opcionesActoParaTriagePaso();
        $this->assertCount(1, $opts);
        $this->assertSame(CodingSystems::SNOMED . '|16310003', $opts[0]['code']);
        $this->assertSame('EcografÃ­a', $opts[0]['label']);
    }

    public function testHidratarDesdeMensajeEcografia(): void
    {
        $catalog = new InMemoryServiceLineActCatalog(
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
        $svc = new SubmitPatientCareRequest(new ResolveCareRequest($catalog), $catalog);
        $draft = [];
        $svc->hidratarDesdeMensaje($draft, 'Necesito una ecografÃ­a');

        $this->assertSame(SubmitPatientCareRequest::TRIAGE_RAIZ_ESTUDIO, $draft['triage_raiz']);
        $this->assertSame(CodingSystems::SNOMED . '|16310003', $draft['pedido_acto']);
        $this->assertSame('11', (string) $draft['id_servicio_asignado']);
        $this->assertSame(CareRequest::MODO_ESTUDIO, $draft[SubmitPatientCareRequest::DRAFT_MODO]);
    }
}
