<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Service;

use common\components\Domain\Clinical\CareRequest\Application\UseCase\SubmitPatientCareRequest;

/**
 * Paso flow-only: elección de acto clínico (estudio/práctica) en Solicitar Atención.
 */
final class CareRequestActStepService
{
    public const STEP_ID = 'pedido_acto';
    public const TITLE = '¿Qué estudio o práctica necesitás?';
    public const DRAFT_FIELD = SubmitPatientCareRequest::DRAFT_ACTO;

    public static function isPedidoActoStep(string $step): bool
    {
        return trim($step) === self::STEP_ID;
    }

    /**
     * @return list<array{code: string, label: string, urgency_band: null, halts_booking: bool}>
     */
    public function opciones(): array
    {
        return (new SubmitPatientCareRequest())->opcionesActoParaTriagePaso();
    }
}
