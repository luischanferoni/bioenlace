<?php

namespace common\components\Domain\Scheduling\Assistant;

use common\components\Domain\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\components\Domain\Scheduling\Service\ReservaTriageServicioSugeridoService;
use common\components\Platform\Assistant\Service\HintCandidateMapper;
use common\components\Platform\Assistant\Service\HintCandidateProviderInterface;
use common\components\Platform\Assistant\Service\HintResolutionContext;
use common\components\Platform\Assistant\Service\HintResolutionMetadata;
use common\models\Scheduling\Turno;

/**
 * Candidatos de hints para reserva de turnos / triage de atención.
 */
final class SchedulingHintCandidateProvider implements HintCandidateProviderInterface
{
    public static function providerKey(): string
    {
        return 'scheduling';
    }

    public static function providesFor(string $entity, HintResolutionContext $ctx): bool
    {
        $entity = strtolower(trim($entity));

        return $entity === 'servicio'
            && HintResolutionMetadata::intentUsesServiciosAceptaTurnos($ctx->intentId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function candidates(string $entity, HintResolutionContext $ctx, ?string $searchQuery): array
    {
        if (!self::providesFor($entity, $ctx)) {
            return [];
        }

        $triageDraft = null;
        $soloHub = false;
        if ($ctx->intentId === HintResolutionMetadata::triageAtencionIntentId()) {
            $tipo = trim((string) ($ctx->draft['tipo_atencion'] ?? ''));
            if ($tipo === Turno::TIPO_ATENCION_PRESENCIAL) {
                $triageDraft = null;
                $soloHub = false;
            } else {
                $triageDraft = ReservaTriageServicioSugeridoService::draftDesdeParamsTriage($ctx->draft);
                $soloHub = $tipo !== '';
            }
        }

        return HintCandidateMapper::mapUiJsonItems(
            ServiciosEfectorAutogestionListadoService::uiJsonItemsServiciosDistintosAceptaTurnos(
                $triageDraft !== [] ? $triageDraft : null,
                $soloHub
            )
        );
    }
}
