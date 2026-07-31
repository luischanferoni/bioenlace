<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Access\CodingSystems;
use common\components\Domain\Clinical\Access\CompositeLineaActoCatalog;
use common\components\Domain\Clinical\Access\EclCapacityCatalog;
use common\components\Domain\Clinical\Access\InMemoryActoEclMembership;
use common\components\Domain\Clinical\Access\InMemoryLineaActoCatalog;
use common\components\Domain\Clinical\Access\PedidoAtencion;
use common\components\Domain\Clinical\Access\PedidoAtencionMetadata;
use common\components\Domain\Clinical\Access\PedidoAtencionService;
use common\models\Servicio;

class PedidoAtencionCapacityEclTest extends Unit
{
    private const IMAGING_ECL = '<< 363679005 |Imaging (procedure)|';

    protected function _before(): void
    {
        PedidoAtencionMetadata::resetCacheForTests();
    }

    public function testCapacityRulesHaveActEcl(): void
    {
        $rules = PedidoAtencionMetadata::capacityRules();
        $this->assertNotEmpty($rules);
        foreach ($rules as $rule) {
            $this->assertNotSame('', trim($rule['act_ecl']));
            $this->assertTrue(
                $rule['specialty_code'] !== null || $rule['match_tipo'] !== null
            );
        }
        $this->assertContains('ECOGRAFIA', PedidoAtencionMetadata::legacyActoAsServicioNames());
        $this->assertTrue(PedidoAtencionMetadata::isLegacyActoServicioNombre('ecografia'));
    }

    public function testEclCapacityResolvesUltrasoundToRadiologySpecialty(): void
    {
        $membership = new InMemoryActoEclMembership([
            CodingSystems::SNOMED . '|16310003' => [self::IMAGING_ECL],
        ]);
        $ecl = new EclCapacityCatalog($membership, [
            [
                'id' => 11,
                'label' => 'RADIOLOGIA',
                'tipo' => Servicio::TIPO_DIAGNOSTICO,
                'specialty_code' => '394914008',
                'specialty_system' => CodingSystems::SNOMED,
                'oferta_modelo' => Servicio::OFERTA_MODELO_INSTITUCIONAL,
            ],
            [
                'id' => 17,
                'label' => 'ECOGRAFIA',
                'tipo' => Servicio::TIPO_DIAGNOSTICO,
                'specialty_code' => '394914008',
                'specialty_system' => CodingSystems::SNOMED,
                'oferta_modelo' => Servicio::OFERTA_MODELO_LEGACY_ACTO,
            ],
        ]);

        $lineas = $ecl->lineasForActo('16310003', CodingSystems::SNOMED, null);
        $this->assertCount(1, $lineas);
        $this->assertSame(11, $lineas[0]['id']);
        $this->assertSame('RADIOLOGIA', $lineas[0]['label']);
    }

    public function testCompositePreferenteExplicitWinsOverEcl(): void
    {
        $membership = new InMemoryActoEclMembership([
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
        $explicit = new InMemoryLineaActoCatalog(
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
        $composite = new CompositeLineaActoCatalog($explicit, $ecl);
        $svc = new PedidoAtencionService($composite);
        $result = $svc->resolve(new PedidoAtencion(
            null,
            '16310003',
            CodingSystems::SNOMED,
            PedidoAtencion::MODO_ESTUDIO
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame(39, $result['pedido']->lineaId);
    }

    public function testCompositeEclOnlyWhenNoBridge(): void
    {
        $membership = new InMemoryActoEclMembership([
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
        $explicit = new InMemoryLineaActoCatalog(
            [
                [
                    'code' => '16310003',
                    'system' => CodingSystems::SNOMED,
                    'display' => 'Diagnostic ultrasonography',
                ],
            ],
            []
        );
        $composite = new CompositeLineaActoCatalog($explicit, $ecl);
        $svc = new PedidoAtencionService($composite);
        $result = $svc->resolve(new PedidoAtencion(
            null,
            '16310003',
            CodingSystems::SNOMED,
            PedidoAtencion::MODO_ESTUDIO
        ));

        $this->assertTrue($result['complete']);
        $this->assertSame(11, $result['pedido']->lineaId);
    }
}
