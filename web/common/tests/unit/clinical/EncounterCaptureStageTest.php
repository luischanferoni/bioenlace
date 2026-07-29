<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Workflow\EncounterCapturePipelineService;
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

    public function testToApiArrayListarEsLivianoYVerTraeReviewSinSnapshot(): void
    {
        $capture = new EncounterCapture();
        $capture->id = 11;
        $capture->client_capture_id = 'client-1';
        $capture->subject_persona_id = 920778;
        $capture->parent_type = 'TURNO';
        $capture->parent_id = 1;
        $capture->stage = EncounterCapture::STAGE_READY_FOR_REVIEW;
        $capture->transcript = 'Control en 7 días';
        $capture->texto_procesado = 'Control en 7 días';
        $capture->created_at = '2026-07-29 10:00:00';
        $capture->updated_at = '2026-07-29 10:05:00';
        $capture->setDatosExtraidos([
            'Indicaciones' => [
                ['Indicacion' => 'Control en consultorio', 'Tipo' => 'follow_up', 'Plazo dias' => 7],
            ],
        ]);
        $capture->setAnalysisResponse([
            'success' => true,
            'message' => 'ok',
            'html' => '<div>legacy</div>',
            'datos' => ['datosExtraidos' => ['ruido' => true]],
            'datosExtraidos' => [
                'Indicaciones' => [
                    ['Indicacion' => 'Control en consultorio', 'Tipo' => 'follow_up', 'Plazo dias' => 7],
                ],
            ],
            'capture_review' => [
                'version' => 1,
                'categories' => [
                    ['title' => 'Indicaciones', 'items' => [['label' => 'Control en consultorio']]],
                ],
            ],
            'puede_confirmar' => true,
            'tiene_datos_faltantes' => false,
            'id_configuracion' => 3,
        ]);

        $svc = new EncounterCapturePipelineService();
        $listItem = $svc->toApiArray($capture, false);
        $this->assertTrue($listItem['has_analysis']);
        $this->assertArrayNotHasKey('analysis', $listItem);
        $this->assertArrayNotHasKey('datosExtraidos', $listItem);
        $this->assertArrayNotHasKey('capture_review', $listItem);
        $this->assertSame('Control en 7 días', $listItem['transcript']);

        $detail = $svc->toApiArray($capture, true);
        $this->assertTrue($detail['has_analysis']);
        $this->assertArrayHasKey('capture_review', $detail);
        $this->assertArrayHasKey('datosExtraidos', $detail);
        $this->assertArrayNotHasKey('analysis', $detail);
        $this->assertArrayNotHasKey('html', $detail);
        $this->assertSame(true, $detail['puede_confirmar']);
        $this->assertSame(3, $detail['id_configuracion']);
        $this->assertSame(
            'Control en consultorio',
            $detail['capture_review']['categories'][0]['items'][0]['label']
        );
    }
}
