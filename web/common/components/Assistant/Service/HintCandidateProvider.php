<?php

namespace common\components\Assistant\Service;

use common\components\Organization\Service\Efectores\EfectoresListadosService;
use common\components\Person\Service\PersonaBusquedaAsistenteUiService;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEnEfectorListadoUiService;
use common\components\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\components\Scheduling\Service\ReservaTriageServicioSugeridoService;
use common\models\Servicio;

/**
 * Universo de candidatos para fuzzy de hints, por entidad de dominio (+ draft/intent), no por action_id de UI.
 */
final class HintCandidateProvider
{
    /**
     * @param string|null $searchQuery Término de búsqueda (p. ej. primer span de la extracción); obligatorio para entidades tipo búsqueda.
     * @return list<array<string, mixed>>
     */
    public static function forEntity(string $entity, HintResolutionContext $ctx, ?string $searchQuery = null): array
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return [];
        }

        switch ($entity) {
            case 'servicio':
                return self::servicioCandidates($ctx);

            case 'efector':
                return self::efectorCandidates($ctx);

            case 'profesional':
                return self::profesionalCandidates($ctx, $searchQuery);

            case 'persona':
                return self::personaCandidates($searchQuery);

            default:
                return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function servicioCandidates(HintResolutionContext $ctx): array
    {
        $triageDraft = null;
        $soloHub = false;
        if ($ctx->intentId === 'atencion.necesito-atencion') {
            $triageDraft = ReservaTriageServicioSugeridoService::draftDesdeParamsTriage($ctx->draft);
            $soloHub = true;
        }

        if (self::intentUsesServiciosAceptaTurnos($ctx->intentId)) {
            return self::mapUiJsonItems(
                ServiciosEfectorAutogestionListadoService::uiJsonItemsServiciosDistintosAceptaTurnos(
                    $triageDraft !== [] ? $triageDraft : null,
                    $soloHub
                )
            );
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
     * Efectores acotados al servicio ya elegido en el draft (mismo criterio que listar-por-servicio).
     *
     * @return list<array<string, mixed>>
     */
    private static function efectorCandidates(HintResolutionContext $ctx): array
    {
        $idServicio = $ctx->draftInt('id_servicio_asignado');
        if ($idServicio <= 0) {
            return [];
        }

        return self::mapUiJsonItems(EfectoresListadosService::itemsForUi(null, [
            'id_servicio' => (string) $idServicio,
            'limit' => '200',
        ]));
    }

    /**
     * Profesionales del efector + servicio del draft que aceptan turnos (mismo criterio que listar-por-efector-servicio-acepta-turnos).
     *
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
        $rows = ProfesionalEnEfectorListadoUiService::autocompletePorEfectorServicio(
            $searchQuery !== null ? trim($searchQuery) : '',
            $filters
        );

        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = isset($r['id']) ? trim((string) $r['id']) : '';
            $text = isset($r['text']) ? trim((string) $r['text']) : '';
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => $text !== '' ? $text : $id,
                'nombre' => $text !== '' ? $text : $id,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function personaCandidates(?string $searchQuery): array
    {
        $q = $searchQuery !== null ? trim($searchQuery) : '';
        if ($q === '') {
            return [];
        }

        return self::mapUiJsonItems(PersonaBusquedaAsistenteUiService::buscar($q));
    }

    private static function intentUsesServiciosAceptaTurnos(string $intentId): bool
    {
        if ($intentId === 'turnos.crear-como-paciente' || $intentId === 'atencion.necesito-atencion') {
            return true;
        }

        return str_starts_with($intentId, 'turnos.');
    }

    /**
     * @param list<array{id: string, name: string}> $items
     * @return list<array<string, mixed>>
     */
    private static function mapUiJsonItems(array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? trim((string) $row['id']) : '';
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => $name,
                'nombre' => $name,
            ];
        }

        return $out;
    }
}
