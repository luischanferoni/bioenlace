<?php

namespace common\tests\unit\clinical;

use frontend\modules\api\v1\controllers\clinical\AppointmentReasonController;
use frontend\modules\api\v1\controllers\clinical\EncounterChatController;
use common\components\Domain\Clinical\Capture\Application\Presentation\CapturePresenter;
use common\models\Clinical\EncounterCapture;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class EncounterRequestIdentifierContractTest extends TestCase
{
    public function testAppointmentReasonRequestsRequireEncounterId(): void
    {
        $controller = (new ReflectionClass(AppointmentReasonController::class))
            ->newInstanceWithoutConstructor();
        $resolve = new ReflectionMethod(AppointmentReasonController::class, 'resolveEncounterIdFromInput');
        $resolve->setAccessible(true);

        self::assertSame(18, $resolve->invoke($controller, ['encounter_id' => 18]));
        self::assertNull($resolve->invoke($controller, ['consulta_id' => 18]));
    }

    public function testEncounterChatRequestsRequireEncounterId(): void
    {
        $controller = (new ReflectionClass(EncounterChatController::class))
            ->newInstanceWithoutConstructor();
        $resolve = new ReflectionMethod(EncounterChatController::class, 'resolveEncounterIdFromBody');
        $resolve->setAccessible(true);

        self::assertSame(27, $resolve->invoke($controller, ['encounter_id' => 27]));
        self::assertSame(0, $resolve->invoke($controller, ['consulta_id' => 27]));
    }

    public function testCaptureResponseUsesOnlyEncounterIdForStoredLegacyAnalysis(): void
    {
        $capture = (new ReflectionClass(EncounterCapture::class))->newInstanceWithoutConstructor();
        $attributes = new \ReflectionProperty(\yii\db\BaseActiveRecord::class, '_attributes');
        $attributes->setAccessible(true);
        $attributes->setValue($capture, [
            'id' => 33,
            'client_capture_id' => 'capture-33',
            'subject_persona_id' => 920778,
            'parent_type' => null,
            'parent_id' => null,
            'encounter_id' => null,
            'stage' => null,
            'audio_relative_path' => null,
            'transcript' => null,
            'texto_procesado' => null,
            'stt_meta_json' => null,
            'staged_item_ids_json' => null,
            'analysis_cache_token' => null,
            'last_error' => null,
            'attempts_stt' => 0,
            'attempts_analysis' => 0,
            'attempts_save' => 0,
            'created_at' => null,
            'updated_at' => null,
            'datos_extraidos_json' => null,
            'analysis_response_json' => json_encode(['id_consulta' => 51]),
        ]);

        $out = (new CapturePresenter())->toApiArray($capture, true);

        self::assertSame(51, $out['encounter_id']);
        self::assertArrayNotHasKey('id_consulta', $out);
    }
}
