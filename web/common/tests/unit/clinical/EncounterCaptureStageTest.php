<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\models\Clinical\EncounterCapture;

class EncounterCaptureStageTest extends Unit
{
    public function testStageCatalog(): void
    {
        $values = EncounterCapture::stageValues();
        $this->assertContains(EncounterCapture::STAGE_UPLOADED, $values);
        $this->assertContains(EncounterCapture::STAGE_TRANSCRIBED, $values);
        $this->assertContains(EncounterCapture::STAGE_READY_FOR_REVIEW, $values);
        $this->assertContains(EncounterCapture::STAGE_COMPLETED, $values);
        $this->assertSame(
            [
                EncounterCapture::STAGE_UPLOADED,
                EncounterCapture::STAGE_STT_FAILED,
                EncounterCapture::STAGE_TRANSCRIBED,
                EncounterCapture::STAGE_ANALYSIS_FAILED,
                EncounterCapture::STAGE_READY_FOR_REVIEW,
                EncounterCapture::STAGE_SAVE_FAILED,
            ],
            EncounterCapture::openStageValues()
        );
    }

    public function testJsonHelpers(): void
    {
        $m = new EncounterCapture();
        $m->setSttMeta(['provenance' => 'device']);
        $this->assertSame('device', $m->getSttMeta()['provenance'] ?? null);
        $m->setStagedItemIds(['a', 'b']);
        $this->assertSame(['a', 'b'], $m->getStagedItemIds());
        $m->setDatosExtraidos(['motivos' => []]);
        $this->assertArrayHasKey('motivos', $m->getDatosExtraidos());
    }
}
