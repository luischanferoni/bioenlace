<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Service\Seed\EfectorDemoSeedService;
use common\components\Domain\Organization\Service\Seed\MedicoMedGeneralEfectorSeedService;

class EfectorDemoSeedServiceConstantsTest extends Unit
{
    public function testCodigosSisaReservadosDentroDelLimite(): void
    {
        foreach (
            [
                EfectorDemoSeedService::COD_SISA_PUBLIC_OTRA_PROV,
                EfectorDemoSeedService::COD_SISA_PRIVATE,
            ] as $codigo
        ) {
            $this->assertLessThanOrEqual(15, strlen($codigo), $codigo);
            $this->assertStringStartsWith('DEV', $codigo);
        }
    }

    public function testDocumentoMedicoSeedParaEfectoresDemoNoExcedeOchoCaracteres(): void
    {
        foreach ([1000, 5000, 99999] as $idEfector) {
            $doc = MedicoMedGeneralEfectorSeedService::documentoReservadoParaEfector($idEfector);
            $this->assertLessThanOrEqual(8, strlen($doc), "id_efector={$idEfector}");
        }
    }
}
