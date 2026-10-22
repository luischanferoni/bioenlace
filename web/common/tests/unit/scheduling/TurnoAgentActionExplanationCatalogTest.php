<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\BehaviorProfile\Application\Catalog\TurnoAgentActionExplanationCatalog;

final class TurnoAgentActionExplanationCatalogTest extends Unit
{
    protected function _after(): void
    {
        TurnoAgentActionExplanationCatalog::resetCacheForTests();
    }

    public function testReturnsExistingCopyForKnownAndUnknownActions(): void
    {
        $catalog = new TurnoAgentActionExplanationCatalog();

        $expected = [
            'extra_confirm_push' => [
                'Pedido adicional de confirmación',
                'Se envió un pedido adicional para confirmar la asistencia al turno.',
            ],
            'shared_confirm_evaluate' => [
                'Evaluación sobre pedido de confirmación',
                'Se evaluó el pedido de confirmación ya enviado según el historial del turno.',
            ],
            'reminder_push' => [
                'Recordatorio de turno',
                'Se envió un recordatorio próximo a la cita.',
            ],
            'release_slot' => [
                'Liberación de cupo',
                'Se evaluó la liberación del cupo según la política vigente.',
            ],
            'skip_low_risk' => [
                'Sin acción adicional',
                'No se aplicó una acción adicional sobre este turno.',
            ],
        ];
        foreach ($expected as $code => [$label, $explanation]) {
            $copy = $catalog->forAction($code, false);
            $this->assertSame($label, $copy['action_label']);
            $this->assertSame($explanation, $copy['explanation_text']);
        }

        $unknown = $catalog->forAction('unknown_action', false);
        $this->assertSame('Acción de acompañamiento de turno', $unknown['action_label']);
        $this->assertSame(
            'Se registró una acción automática relacionada con este turno.',
            $unknown['explanation_text']
        );
    }

    public function testPreservesGeneralMessagesAndSupersededSnapshotCopy(): void
    {
        $catalog = new TurnoAgentActionExplanationCatalog();
        $copy = $catalog->forAction('reminder_push', true);

        $this->assertSame('No hay una acción registrada para este turno.', $catalog->unavailableMessage());
        $this->assertSame(
            'Esta explicación describe hechos registrados y la acción aplicada. No es una calificación personal.',
            $copy['disclaimer']
        );
        $this->assertSame(
            'Se envió un recordatorio próximo a la cita. La información de historial usada en ese momento ya fue actualizada.',
            $copy['explanation_text']
        );
    }
}
