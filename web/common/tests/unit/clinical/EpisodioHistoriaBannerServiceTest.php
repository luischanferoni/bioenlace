<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Service\EpisodioHistoriaBannerService;
use common\models\Clinical\Encounter;

class EpisodioHistoriaBannerServiceTest extends Unit
{
    public function testEpisodePresentationProvidesBackendLabelAndIntent(): void
    {
        $this->assertSame(
            ['label' => 'Guardia', 'intent' => 'danger'],
            EpisodioHistoriaBannerService::episodePresentation(Encounter::PARENT_GUARDIA)
        );
        $this->assertSame(
            ['label' => 'Internación', 'intent' => 'info'],
            EpisodioHistoriaBannerService::episodePresentation(Encounter::PARENT_INTERNACION)
        );
    }

    public function testUnknownEpisodeTypeHasNoImplicitPresentation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EpisodioHistoriaBannerService::episodePresentation('UNKNOWN');
    }
}
