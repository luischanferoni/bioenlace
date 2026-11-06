<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Identity\Infrastructure\External\Mpi\MpiApiClient;
use common\components\Domain\Person\Identity\Infrastructure\External\Puco\PucoApiClient;
use common\components\Domain\Person\Identity\Infrastructure\External\Puco\PucoUnavailableException;

/**
 * Contrato de PUCO: array = tiene cobertura, null = no tiene (o no consultable),
 * excepción = el servicio falló.
 */
class PucoApiClientTest extends Unit
{
    public function testConCoberturaDevuelveElPrimerRegistro(): void
    {
        $mpi = $this->mpiReturning([
            'successful' => true,
            'statusCode' => 200,
            'data' => [
                ['NombreObraSocial' => 'OSDE', 'NombreYApellido' => 'PEREZ JUAN'],
                ['NombreObraSocial' => 'SWISS', 'NombreYApellido' => 'PEREZ JUAN'],
            ],
        ]);

        $cobertura = (new PucoApiClient($mpi))->lookup('30111222');

        $this->assertSame(['NombreObraSocial' => 'OSDE', 'NombreYApellido' => 'PEREZ JUAN'], $cobertura);
        $this->assertSame('coberturas?dni=30111222&sexo=1', $mpi->ultimaRuta);
    }

    public function testSinCoberturaDevuelveNull(): void
    {
        $mpi = $this->mpiReturning(['successful' => true, 'statusCode' => 200, 'data' => []]);

        $this->assertNull((new PucoApiClient($mpi))->lookup('30111222'));
    }

    public function testServicioCaidoLanzaExcepcion(): void
    {
        $mpi = $this->mpiReturning(null);

        $this->expectException(PucoUnavailableException::class);

        (new PucoApiClient($mpi))->lookup('30111222');
    }

    public function testRespuestaConErrorDelProveedorLanzaExcepcion(): void
    {
        $mpi = $this->mpiReturning([
            'successful' => false,
            'statusCode' => 400,
            'message' => 'dni inválido',
            'data' => [],
        ]);

        $this->expectException(PucoUnavailableException::class);
        $this->expectExceptionMessage('P.U.C.O. respondió 400');

        (new PucoApiClient($mpi))->lookup('30111222');
    }

    public function testDocumentoNoConsultableNoLlamaAlProveedor(): void
    {
        $mpi = $this->mpiReturning(['successful' => true, 'statusCode' => 200, 'data' => []]);

        $this->assertNull((new PucoApiClient($mpi))->lookup('X8673354'));
        $this->assertNull($mpi->ultimaRuta, 'Con letras no se llama a SEIPA (tipa dni como Long).');
    }

    public function testElDocumentoSeEscapaEnLaRuta(): void
    {
        $mpi = $this->mpiReturning(['successful' => true, 'statusCode' => 200, 'data' => []]);

        (new PucoApiClient($mpi))->lookup(' 0030111222 ');

        $this->assertSame('coberturas?dni=30111222&sexo=1', $mpi->ultimaRuta);
    }

    /** @param array<string, mixed>|null $respuesta */
    private function mpiReturning(?array $respuesta): MpiApiClient
    {
        return new class ($respuesta) extends MpiApiClient {
            public ?string $ultimaRuta = null;

            /** @param array<string, mixed>|null $respuesta */
            public function __construct(private ?array $respuesta)
            {
                parent::__construct();
            }

            public function call(string $metodo, $json = '{}', string $verb = 'GET'): ?array
            {
                $this->ultimaRuta = $metodo;

                return $this->respuesta;
            }
        };
    }
}
