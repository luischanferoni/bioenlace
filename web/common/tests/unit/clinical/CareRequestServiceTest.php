<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CareRequest\Domain\CodingSystems;
use common\components\Domain\Clinical\CareRequest\Domain\InMemoryServiceLineActCatalog;
use common\components\Domain\Clinical\CareRequest\Domain\Model\CareRequest;
use common\components\Domain\Clinical\CareRequest\Application\Service\CareRequestMetadata;
use common\components\Domain\Clinical\CareRequest\Application\UseCase\ResolveCareRequest;
use common\models\Clinical\Input\DerivacionInput;
use common\models\Clinical\ConsultaDerivaciones;

class ResolveCareRequestTest extends Unit
{
    protected function _before(): void
    {
        CareRequestMetadata::resetCacheForTests();
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
        $catalog = new InMemoryServiceLineActCatalog(
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
        $svc = new ResolveCareRequest($catalog);
        $result = $svc->resolve(new CareRequest(10, null, null, CareRequest::MODO_INTERCONSULTA));

        $this->assertTrue($result['complete']);
        $this->assertSame(10, $result['pedido']->lineaId);
        $this->assertSame('183515008', $result['pedido']->actoCode);
        $this->assertSame(CodingSystems::SNOMED, $result['pedido']->actoSystem);
    }

    public function testResolveSoloActoUnaLinea(): void
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
        $svc = new ResolveCareRequest($catalog);
        $result = $svc->resolve(new CareRequest(
            null,
            '16310003',
            CodingSystems::SNOMED,
            CareRequest::MODO_ESTUDIO
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame(11, $result['pedido']->lineaId);
        $this->assertSame('16310003', $result['pedido']->actoCode);
    }

    public function testResolveSoloActoVariasLineasDevuelveCandidatos(): void
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
        $svc = new ResolveCareRequest($catalog);
        $result = $svc->resolve(new CareRequest(
            null,
            '16310003',
            CodingSystems::SNOMED,
            CareRequest::MODO_ESTUDIO
        ));

        $this->assertFalse($result['complete']);
        $this->assertContains('linea', $result['missing']);
        $this->assertCount(2, $result['candidates']['lineas']);
    }

    public function testResolveAmbosCompleto(): void
    {
        $catalog = new InMemoryServiceLineActCatalog(
            [
                [
                    'code' => '91251008',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Physical therapy procedure',
                ],
            ],
            []
        );
        $svc = new ResolveCareRequest($catalog);
        $result = $svc->resolve(new CareRequest(
            10,
            '91251008',
            CodingSystems::SNOMED,
            CareRequest::MODO_PRACTICA,
            null,
            null,
            'Physical therapy procedure'
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame([], $result['missing']);
    }

    public function testDescartaCodeSystemLocal(): void
    {
        $svc = new ResolveCareRequest(new InMemoryServiceLineActCatalog());
        $result = $svc->resolve(new CareRequest(
            null,
            'eco_abdominal',
            'local',
            CareRequest::MODO_ESTUDIO
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
            DerivacionInput::referralKindForModo(CareRequest::MODO_ESTUDIO)
        );
        $this->assertSame(
            ConsultaDerivaciones::INTERCONSULTA,
            DerivacionInput::referralKindForModo(CareRequest::MODO_INTERCONSULTA)
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
        $this->assertSame(CareRequest::MODO_ESTUDIO, $input->modo);
    }

    public function testMetadataDefaultsLoaded(): void
    {
        $default = CareRequestMetadata::defaultActoForModo(CareRequest::MODO_INTERCONSULTA);
        $this->assertNotNull($default);
        $this->assertSame('183515008', $default['code']);
        $this->assertSame(CodingSystems::SNOMED, $default['code_system']);
        $this->assertContains(CodingSystems::SNOMED, CareRequestMetadata::allowedSystems());
        $this->assertNotContains('local', CareRequestMetadata::allowedSystems());
    }

    public function testLineaNlAliasClinicoMapsToInternalMedicine(): void
    {
        $tipologia = CareRequestMetadata::resolveLineaSpecialtyFromNl('clínico');
        $this->assertNotNull($tipologia);
        $this->assertSame('394807007', $tipologia['specialty_code']);
        $this->assertSame(CodingSystems::SNOMED, $tipologia['specialty_system']);

        $tipologia = CareRequestMetadata::resolveLineaSpecialtyFromNl('medico general');
        $this->assertNotNull($tipologia);
        $this->assertSame('394814009', $tipologia['specialty_code']);

        $this->assertNull(CareRequestMetadata::resolveLineaSpecialtyFromNl('algo inventado xyz'));
    }

    public function testActoNlAliasEcografiaEnEspanol(): void
    {
        $this->assertSame(
            'Ecografía',
            CareRequestMetadata::patientLabelForActo('16310003', CodingSystems::SNOMED)
        );
        $this->assertTrue(CareRequestMetadata::nlTextHitsKey('Necesito una ecografía', 'ecografia'));
        $this->assertSame('Estudio de imagen', CareRequestMetadata::defaultActoForModo(CareRequest::MODO_ESTUDIO)['display'] ?? null);
    }
}
