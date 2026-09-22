<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CareRequest\Domain\CodingSystems;
use common\components\Domain\Clinical\CareRequest\Domain\CompositeServiceLineActCatalog;
use common\components\Domain\Clinical\CareRequest\Domain\EclCapacityCatalog;
use common\components\Domain\Clinical\CareRequest\Infrastructure\External\InMemoryActEclMembership;
use common\components\Domain\Clinical\CareRequest\Domain\InMemoryServiceLineActCatalog;
use common\components\Domain\Clinical\CareRequest\Domain\Model\CareRequest;
use common\components\Domain\Clinical\CareRequest\Application\Service\CareRequestMetadata;
use common\components\Domain\Clinical\CareRequest\Application\UseCase\ResolveCareRequest;
use common\models\Organization\Servicio;

class CareRequestCapacityEclTest extends Unit
{
    private const IMAGING_ECL = '<< 363679005 |Imaging (procedure)|';

    protected function _before(): void
    {
        CareRequestMetadata::resetCacheForTests();
    }

    public function testCapacityRulesHaveActEcl(): void
    {
        $rules = CareRequestMetadata::capacityRules();
        $this->assertNotEmpty($rules);
        foreach ($rules as $rule) {
            $this->assertNotSame('', trim($rule['act_ecl']));
            $this->assertTrue(
                $rule['specialty_code'] !== null || $rule['match_tipo'] !== null
            );
        }
    }

    public function testEclCapacityResolvesUltrasoundToRadiologySpecialty(): void
    {
        $membership = new InMemoryActEclMembership([
            CodingSystems::SNOMED . '|16310003' => [self::IMAGING_ECL],
        ]);
        $ecl = new EclCapacityCatalog($membership, [
            [
                'id' => 11,
                'label' => 'RADIOLOGIA',
                'tipo' => Servicio::TIPO_DIAGNOSTICO,
                'specialty_code' => '394914008',
                'specialty_system' => CodingSystems::SNOMED,
            ],
            [
                'id' => 26,
                'label' => 'ADMINISTRACION',
                'tipo' => Servicio::TIPO_SOPORTE,
                'specialty_code' => null,
                'specialty_system' => null,
            ],
        ]);

        $lineas = $ecl->lineasForActo('16310003', CodingSystems::SNOMED, null);
        $this->assertCount(1, $lineas);
        $this->assertSame(11, $lineas[0]['id']);
        $this->assertSame('RADIOLOGIA', $lineas[0]['label']);
    }

    public function testCompositePreferenteExplicitWinsOverEcl(): void
    {
        $membership = new InMemoryActEclMembership([
            CodingSystems::SNOMED . '|16310003' => [self::IMAGING_ECL],
        ]);
        $ecl = new EclCapacityCatalog($membership, [
            [
                'id' => 11,
                'label' => 'RADIOLOGIA',
                'tipo' => Servicio::TIPO_DIAGNOSTICO,
                'specialty_code' => '394914008',
                'specialty_system' => CodingSystems::SNOMED,
            ],
            [
                'id' => 39,
                'label' => 'DIAGNOSTICO POR IMAGENES',
                'tipo' => Servicio::TIPO_DIAGNOSTICO,
                'specialty_code' => '394914008',
                'specialty_system' => CodingSystems::SNOMED,
            ],
        ]);
        $explicit = new InMemoryServiceLineActCatalog(
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
                    'preferente' => true,
                ],
            ]
        );
        $composite = new CompositeServiceLineActCatalog($explicit, $ecl);
        $svc = new ResolveCareRequest($composite);
        $result = $svc->resolve(new CareRequest(
            null,
            '16310003',
            CodingSystems::SNOMED,
            CareRequest::MODO_ESTUDIO
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame(39, $result['pedido']->lineaId);
    }

    public function testCompositeEclOnlyWhenNoBridge(): void
    {
        $membership = new InMemoryActEclMembership([
            CodingSystems::SNOMED . '|16310003' => [self::IMAGING_ECL],
        ]);
        $ecl = new EclCapacityCatalog($membership, [
            [
                'id' => 11,
                'label' => 'RADIOLOGIA',
                'tipo' => Servicio::TIPO_DIAGNOSTICO,
                'specialty_code' => '394914008',
                'specialty_system' => CodingSystems::SNOMED,
            ],
        ]);
        $explicit = new InMemoryServiceLineActCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
            []
        );
        $composite = new CompositeServiceLineActCatalog($explicit, $ecl);
        $svc = new ResolveCareRequest($composite);
        $result = $svc->resolve(new CareRequest(
            null,
            '16310003',
            CodingSystems::SNOMED,
            CareRequest::MODO_ESTUDIO
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame(11, $result['pedido']->lineaId);
    }
}
