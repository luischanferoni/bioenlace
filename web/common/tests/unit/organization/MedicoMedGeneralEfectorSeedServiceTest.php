<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Service\Seed\MedicoMedGeneralEfectorSeedService;

class MedicoMedGeneralEfectorSeedServiceTest extends Unit
{
    public function testDocumentoEfector863Reservado(): void
    {
        $identity = MedicoMedGeneralEfectorSeedService::expectedIdentity(863);

        $this->assertSame('39999863', $identity['documento']);
        $this->assertSame('medico_med_general_863', $identity['username']);
    }

    /**
     * @dataProvider documentoReservadoProvider
     */
    public function testDocumentoReservadoTieneMaximoOchoCaracteres(int $idEfector): void
    {
        $documento = MedicoMedGeneralEfectorSeedService::documentoReservadoParaEfector($idEfector);

        $this->assertLessThanOrEqual(8, strlen($documento), "id_efector={$idEfector}");
        $this->assertMatchesRegularExpression('/^\d{8}$/', $documento);
    }

    public static function documentoReservadoProvider(): array
    {
        return [
            'efector chico' => [864],
            'efector 863 legacy' => [863],
            'efector mil' => [1000],
            'efector grande' => [123456],
            'efector muy grande modulo' => [9999999],
        ];
    }

    public function testDocumentoMilEsOchoCaracteres(): void
    {
        $this->assertSame('39001000', MedicoMedGeneralEfectorSeedService::documentoReservadoParaEfector(1000));
    }

    public function testUsernameIncluyeIdEfector(): void
    {
        $identity = MedicoMedGeneralEfectorSeedService::expectedIdentity(1234);

        $this->assertSame('medico_med_general_1234', $identity['username']);
    }
}
