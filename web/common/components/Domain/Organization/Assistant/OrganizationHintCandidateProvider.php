<?php

namespace common\components\Domain\Organization\Assistant;

use common\components\Domain\Organization\Service\Efectores\EfectoresListadosService;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;
use common\components\Platform\Assistant\Service\HintCandidateMapper;
use common\components\Platform\Assistant\Service\HintCandidateProviderInterface;
use common\components\Platform\Assistant\Service\HintResolutionContext;
use common\components\Platform\Assistant\Service\HintResolutionMetadata;
use common\models\Servicio;

/**
 * Candidatos de hints para servicios, efectores y profesionales del centro.
 */
final class OrganizationHintCandidateProvider implements HintCandidateProviderInterface
{
    public static function providerKey(): string
    {
        return 'organization';
    }

    public static function providesFor(string $entity, HintResolutionContext $ctx): bool
    {
        $entity = strtolower(trim($entity));
        if ($entity === 'servicio') {
            return !HintResolutionMetadata::intentUsesServiciosAceptaTurnos($ctx->intentId);
        }

        return in_array($entity, ['efector', 'profesional'], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function candidates(string $entity, HintResolutionContext $ctx, ?string $searchQuery): array
    {
        $entity = strtolower(trim($entity));

        return match ($entity) {
            'servicio' => self::servicioCandidates($ctx),
            'efector' => self::efectorCandidates($ctx),
            'profesional' => self::profesionalCandidates($ctx, $searchQuery),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function servicioCandidates(HintResolutionContext $ctx): array
    {
        if (!self::providesFor('servicio', $ctx)) {
            return [];
        }

        $rows = Servicio::find()->orderBy(['nombre' => SORT_ASC])->all();
        $out = [];
        foreach ($rows as $s) {
            $out[] = [
                'id' => (string) (int) $s->id_servicio,
                'name' => (string) $s->nombre,
                'nombre' => (string) $s->nombre,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function efectorCandidates(HintResolutionContext $ctx): array
    {
        $idServicio = $ctx->draftInt('id_servicio_asignado');
        if ($idServicio <= 0) {
            return [];
        }

        return HintCandidateMapper::mapUiJsonItems(EfectoresListadosService::itemsForUi(null, [
            'id_servicio' => (string) $idServicio,
            'limit' => '200',
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function profesionalCandidates(HintResolutionContext $ctx, ?string $searchQuery): array
    {
        $idEfector = $ctx->draftInt('id_efector');
        $idServicio = $ctx->draftInt('id_servicio_asignado');
        if ($idEfector <= 0 || $idServicio <= 0) {
            return [];
        }

        $filters = [
            'id_efector' => (string) $idEfector,
            'id_servicio' => (string) $idServicio,
            'acepta_turnos' => 'SI',
            'limit' => 200,
        ];
        $tipoAtencion = trim((string) ($ctx->draft['tipo_atencion'] ?? ''));
        if ($tipoAtencion !== '') {
            $filters['tipo_atencion'] = $tipoAtencion;
        }

        return HintCandidateMapper::mapAutocompleteRows(
            ProfesionalEnEfectorListadoUiService::autocompletePorEfectorServicio(
                $searchQuery !== null ? trim($searchQuery) : '',
                $filters
            )
        );
    }
}
