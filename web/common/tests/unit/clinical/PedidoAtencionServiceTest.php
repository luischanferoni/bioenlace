<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Access\CodingSystems;
use common\components\Domain\Clinical\Access\InMemoryLineaActoCatalog;
use common\components\Domain\Clinical\Access\PedidoAtencion;
use common\components\Domain\Clinical\Access\PedidoAtencionMetadata;
use common\components\Domain\Clinical\Access\PedidoAtencionService;
use common\models\Clinical\Input\DerivacionInput;
use common\models\ConsultaDerivaciones;

class PedidoAtencionServiceTest extends Unit
{
    protected function _before(): void
    {
        PedidoAtencionMetadata::resetCacheForTests();
    }

    public function testCodingSystemsRejectLocal(): void
    {
        $this->assertTrue(CodingSystems::isAllowed(CodingSystems::SNOMED));
        $this->assertTrue(CodingSystems::isAllowed(CodingSystems::LOINC));
        $this->assertFalse(CodingSystems::isAllowed('local'));
        $this->assertFalse(CodingSystems::isAllowed('bioenlace'));
    }

    public function testResolveSoloLineaAplicaDefaultInterconsulta(): void
    {
        $catalog = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '183515008',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Referral to physician',
                ],
                [
                    'code' => '11429006',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Consultation',
                ],
            ],
            [
                [
                    'linea_id' => 10,
                    'linea_label' => 'OFTALMOLOGIA',
                    'code' => '183515008',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => false,
                ],
                [
                    'linea_id' => 10,
                    'linea_label' => 'OFTALMOLOGIA',
                    'code' => '11429006',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => true,
                ],
            ]
        );
        $svc = new PedidoAtencionService($catalog);
        $result = $svc->resolve(new PedidoAtencion(10, null, null, PedidoAtencion::MODO_INTERCONSULTA));

        $this->assertTrue($result['complete']);
        $this->assertSame(10, $result['pedido']->lineaId);
        $this->assertSame('183515008', $result['pedido']->actoCode);
        $this->assertSame(CodingSystems::SNOMED, $result['pedido']->actoSystem);
    }

    public function testResolveSoloActoUnaLinea(): void
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
        $svc = new PedidoAtencionService($catalog);
        $result = $svc->resolve(new PedidoAtencion(
            null,
            '16310003',
            CodingSystems::SNOMED,
            PedidoAtencion::MODO_ESTUDIO
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame(11, $result['pedido']->lineaId);
        $this->assertSame('16310003', $result['pedido']->actoCode);
    }

    public function testResolveSoloActoVariasLineasDevuelveCandidatos(): void
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
                    'linea_label' => 'DIAGNOSTICO POR IMAGENES',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => false,
                ],
                [
                    'linea_id' => 11,
                    'linea_label' => 'RADIOLOGIA',
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'preferente' => false,
                ],
            ]
        );
        $svc = new PedidoAtencionService($catalog);
        $result = $svc->resolve(new PedidoAtencion(
            null,
            '16310003',
            CodingSystems::SNOMED,
            PedidoAtencion::MODO_ESTUDIO
        ));

        $this->assertFalse($result['complete']);
        $this->assertContains('linea', $result['missing']);
        $this->assertCount(2, $result['candidates']['lineas']);
    }

    public function testResolveAmbosCompleto(): void
    {
        $catalog = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '91251008',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Physical therapy procedure',
                ],
            ],
            []
        );
        $svc = new PedidoAtencionService($catalog);
        $result = $svc->resolve(new PedidoAtencion(
            10,
            '91251008',
            CodingSystems::SNOMED,
            PedidoAtencion::MODO_PRACTICA,
            null,
            null,
            'Physical therapy procedure'
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame([], $result['missing']);
    }

    public function testDescartaCodeSystemLocal(): void
    {
        $svc = new PedidoAtencionService(new InMemoryLineaActoCatalog());
        $result = $svc->resolve(new PedidoAtencion(
            null,
            'eco_abdominal',
            'local',
            PedidoAtencion::MODO_ESTUDIO
        ));

        $this->assertFalse($result['complete']);
        $this->assertContains('acto', $result['missing']);
        $this->assertNull($result['pedido']->actoCode);
    }

    public function testDerivacionInputMissingLineaWhenUnresolved(): void
    {
        $input = DerivacionInput::fromExtractedRow([
            'Indicaciones' => 'control',
        ]);
        $missing = $input->missingFieldsForCompleteness();
        $this->assertContains(DerivacionInput::FIELD_SERVICIO, $missing);
    }

    public function testDerivacionInputReferralKindForModo(): void
    {
        $this->assertSame(
            ConsultaDerivaciones::PRACTICA,
            DerivacionInput::referralKindForModo(PedidoAtencion::MODO_ESTUDIO)
        );
        $this->assertSame(
            ConsultaDerivaciones::INTERCONSULTA,
            DerivacionInput::referralKindForModo(PedidoAtencion::MODO_INTERCONSULTA)
        );
    }

    public function testDerivacionInputParsesActoCode(): void
    {
        $input = DerivacionInput::fromExtractedRow([
            'id_servicio' => 11,
            'codigo' => '16310003',
            'code_system' => CodingSystems::SNOMED,
            'tipo' => 'estudio',
        ]);
        $this->assertSame('16310003', $input->actoCode);
        $this->assertSame(CodingSystems::SNOMED, $input->actoSystem);
        $this->assertSame(PedidoAtencion::MODO_ESTUDIO, $input->modo);
    }

    public function testMetadataDefaultsLoaded(): void
    {
        $default = PedidoAtencionMetadata::defaultActoForModo(PedidoAtencion::MODO_INTERCONSULTA);
        $this->assertNotNull($default);
        $this->assertSame('183515008', $default['code']);
        $this->assertSame(CodingSystems::SNOMED, $default['code_system']);
        $this->assertContains(CodingSystems::SNOMED, PedidoAtencionMetadata::allowedSystems());
        $this->assertNotContains('local', PedidoAtencionMetadata::allowedSystems());
    }

    public function testLineaNlAliasClinicoMapsToInternalMedicine(): void
    {
        $tipologia = PedidoAtencionMetadata::resolveLineaSpecialtyFromNl('clínico');
        $this->assertNotNull($tipologia);
        $this->assertSame('394807007', $tipologia['specialty_code']);
        $this->assertSame(CodingSystems::SNOMED, $tipologia['specialty_system']);

        $tipologia = PedidoAtencionMetadata::resolveLineaSpecialtyFromNl('medico general');
        $this->assertNotNull($tipologia);
        $this->assertSame('394814009', $tipologia['specialty_code']);

        $this->assertNull(PedidoAtencionMetadata::resolveLineaSpecialtyFromNl('algo inventado xyz'));
    }

    public function testActoNlAliasEcografiaEnEspanol(): void
    {
        $this->assertSame(
            'Ecografía',
            PedidoAtencionMetadata::patientLabelForActo('16310003', CodingSystems::SNOMED)
        );
        $this->assertTrue(PedidoAtencionMetadata::nlTextHitsKey('Necesito una ecografía', 'ecografia'));
        $this->assertSame('Estudio de imagen', PedidoAtencionMetadata::defaultActoForModo(PedidoAtencion::MODO_ESTUDIO)['display'] ?? null);
    }
}
