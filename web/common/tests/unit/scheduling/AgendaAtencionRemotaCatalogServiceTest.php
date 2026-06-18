<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\AgendaAtencionRemotaCatalogService;

class AgendaAtencionRemotaCatalogServiceTest extends Unit
{
    protected function _before(): void
    {
        AgendaAtencionRemotaCatalogService::resetCache();
    }

    public function testCatalogoDeclaraCampoAgenda(): void
    {
        $catalog = new AgendaAtencionRemotaCatalogService();
        $this->assertSame('', $catalog->mensajeInfoConfigurarAgenda());
        $campo = $catalog->campoAceptaConsultasOnline();
        $this->assertNotSame('', $campo['label']);
        $this->assertNotSame('', $campo['hint']);
    }

    public function testInsightAgendaConfigTieneActionId(): void
    {
        $cfg = (new AgendaAtencionRemotaCatalogService())->insightAgendaConfig();
        $this->assertSame('profesional-agenda.configurar-propio', $cfg['action_id']);
        $this->assertNotSame('', $cfg['link_label']);
    }
}
