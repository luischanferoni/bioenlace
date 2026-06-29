<?php

namespace common\tests\unit\integrations;

use Codeception\Test\Unit;
use common\components\Domain\Integrations\Mpi\MpiDomicilioNormalizer;

class MpiDomicilioNormalizerTest extends Unit
{
    public function testNormalizeFlatRow(): void
    {
        $response = [
            'successful' => 1,
            'statusCode' => 200,
            'data' => [
                [
                    'id_provincia' => '06',
                    'provincia' => 'BUENOS AIRES',
                    'localidad' => 'LA PLATA',
                    'calle' => 'CALLE 7',
                    'numero' => '1234',
                ],
            ],
        ];

        $row = MpiDomicilioNormalizer::normalizeResponse($response);
        $this->assertIsArray($row);
        $this->assertSame('06', $row['id_provincia']);
        $this->assertSame('CALLE 7', $row['calle']);
    }

    public function testNormalizeResidenciaNode(): void
    {
        $response = [
            'successful' => 1,
            'statusCode' => 200,
            'data' => [
                'paciente' => [
                    'set_ampliado' => [
                        'residencia' => [
                            'provincia' => ['id' => '82', 'texto' => 'SANTA FE'],
                            'departamento' => ['id' => '82021', 'texto' => 'ROSARIO'],
                            'localidad' => ['id' => '82021010', 'texto' => 'ROSARIO'],
                            'calle' => 'SAN MARTIN',
                            'numero' => '500',
                        ],
                    ],
                ],
            ],
        ];

        $row = MpiDomicilioNormalizer::normalizeResponse($response);
        $this->assertIsArray($row);
        $this->assertSame('82', $row['id_provincia']);
        $this->assertSame('SANTA FE', $row['provincia']);
        $this->assertSame('ROSARIO', $row['localidad']);
        $this->assertSame('SAN MARTIN', $row['calle']);
    }

    public function testReturnsNullWhenNoDomicilioData(): void
    {
        $this->assertNull(MpiDomicilioNormalizer::normalizeResponse(null));
        $this->assertNull(MpiDomicilioNormalizer::normalizeResponse([
            'successful' => 0,
            'statusCode' => 404,
            'data' => [],
        ]));
    }
}
