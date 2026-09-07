<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

/**
 * Adapta preprocess → shape 1ª IA v1 (campos nativos o inferidos).
 */
final class AssistantFirstIaAdapter
{
    /** Destino usable para agenda pura (oferta / profesional / “para mi …”). */
    private const DESTINO_AGENDA = '/\b(con|en|para)\s+\S+/u';

    /** Gestión de turno ya existente (no es pedido bare de reserva). */
    private const GESTION_TURNO_EXISTENTE = '/\b(cancelar|anular|dar de baja|reprogramar|mover|cambiar el turno|confirmar (el )?turno|confirmar asistencia)\b/u';

    /**
     * @param array<string, mixed> $preprocess
     * @return array{
     *   normalized_text: string,
     *   necesidad_usuario: string,
     *   routing_hint: string,
     *   tags: list<string>,
     *   context_areas: list<string>,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>,
     *   intent_ids_hint: list<string>
     * }
     */
    public static function fromPreprocess(array $preprocess, string $rawContent = ''): array
    {
        $normalized = trim((string) ($preprocess['normalized_text'] ?? $rawContent));
        $areas = ChatPreprocessService::normalizeContextAreas($preprocess['context_areas'] ?? []);
        $extractions = is_array($preprocess['extractions'] ?? null) ? $preprocess['extractions'] : [];
        $goal = ChatPreprocessService::canonicalizeGoal((string) ($preprocess['user_goal'] ?? 'ambiguous'));

        $iaTags = ChatPreprocessService::normalizeTags($preprocess['tags'] ?? []);
        $tags = array_values(array_unique(array_merge(
            $iaTags,
            self::inferOperationalTags($normalized, $areas),
            self::inferSoftTags($normalized, $goal, $iaTags)
        )));
        $tags = self::reconcileAgendaTags($tags, $normalized);

        $actionText = trim((string) ($preprocess['action_text'] ?? ''));
        $necesidad = trim((string) ($preprocess['necesidad_usuario'] ?? ''));
        if ($necesidad === '') {
            $necesidad = $actionText !== '' ? $actionText : $normalized;
        }

        $routingHint = ChatPreprocessService::canonicalizeRoutingHint((string) ($preprocess['routing_hint'] ?? ''));
        if ($routingHint === 'dudosa' && isset($preprocess['user_goal'])) {
            $routingHint = self::routingHintFromGoal(
                ChatPreprocessService::canonicalizeGoal((string) $preprocess['user_goal'])
            );
        }

        $intentHints = [];
        if (isset($preprocess['intent_ids_hint']) && is_array($preprocess['intent_ids_hint'])) {
            foreach ($preprocess['intent_ids_hint'] as $id) {
                if (is_string($id) && trim($id) !== '') {
                    $intentHints[] = trim($id);
                }
            }
        }

        return [
            'normalized_text' => $normalized,
            'necesidad_usuario' => $necesidad,
            'routing_hint' => $routingHint,
            'tags' => $tags,
            'context_areas' => $areas,
            'extractions' => $extractions,
            'intent_ids_hint' => $intentHints,
        ];
    }

    /**
     * Tags que separan puertas del smart-catalog (A/B/C vs ver-turnos). Siempre.
     *
     * @param list<string> $areas
     * @return list<string>
     */
    private static function inferOperationalTags(string $normalized, array $areas): array
    {
        $tags = [];
        foreach ($areas as $area) {
            $tags[] = $area;
        }

        if ($normalized === '') {
            return array_values(array_unique($tags));
        }

        $folded = ChatChannelPolicy::fold($normalized);

        if (preg_match(self::GESTION_TURNO_EXISTENTE, $folded)) {
            $tags[] = 'cancelar_turno';
        }

        if (preg_match(
            '/\b(mis analisis|mis análisis|mis resultados|resultados de laboratorio|informes de laboratorio|ver mis estudios)\b/u',
            $folded
        )) {
            $tags[] = 'mis_analisis';
        }

        if (ChatChannelPolicy::isStudyOrPracticeRequest($normalized)) {
            $tags[] = 'estudio';
        } elseif (
            !self::isGestionTurnoExistente($folded)
            && ChatChannelPolicy::requestsOperationalTramiteExecution($normalized)
            && ChatChannelPolicy::isSchedulingRequest($normalized)
            && !ChatChannelPolicy::isClinicalSymptomContent($normalized)
        ) {
            if (self::hasAgendaDestino($folded)) {
                $tags[] = 'sacar_turno';
            } else {
                $tags[] = 'pedido_turno_sin_destino';
            }
        }

        if (preg_match(
            '/\b(mis turnos|mis citas|que turnos tengo|qué turnos tengo|proximos? turnos|próximos? turnos|turnos pendientes)\b/u',
            $folded
        ) && !self::isGestionTurnoExistente($folded)) {
            $tags[] = 'mis_turnos';
        }

        if (preg_match(
            '/\b(ultima vez que fui|última vez que fui|cuando fui al|cuándo fui al|cuando fue la ultima|cuándo fue la última)\b/u',
            $folded
        )) {
            $tags[] = 'ultima_vez_oferta';
        }

        if (preg_match(
            '/\b(controlar|control de seguimiento|pedir un control|seguimiento tratamiento|renovar medicacion|renovar medicación|consulta por mensaje)\b/u',
            $folded
        )) {
            $tags[] = 'control';
        }

        return array_values(array_unique($tags));
    }

    private static function hasAgendaDestino(string $folded): bool
    {
        return (bool) preg_match(self::DESTINO_AGENDA, $folded);
    }

    private static function isGestionTurnoExistente(string $folded): bool
    {
        return (bool) preg_match(self::GESTION_TURNO_EXISTENTE, $folded);
    }

    /**
     * Heurísticas blandas. Síntoma clínico siempre (aunque la 1ª IA haya etiquetado otra cosa).
     * El resto no pisa si la IA ya trajo tags propios.
     *
     * @param list<string> $iaTags
     * @return list<string>
     */
    private static function inferSoftTags(string $normalized, string $goal, array $iaTags): array
    {
        $tags = [];
        if ($normalized === '') {
            return $tags;
        }

        if (ChatPreprocessService::isClinicalSymptomContent($normalized)) {
            $tags[] = 'sintoma';
            $tags[] = 'necesito_atencion';
        }

        if ($iaTags !== []) {
            return array_values(array_unique($tags));
        }

        if (ChatChannelPolicy::isAppointmentPolicyQuestion($normalized)) {
            $tags[] = 'llegar_tarde';
        }
        if (preg_match('/\b(medium|horoscop|clima)\b/u', mb_strtolower($normalized, 'UTF-8'))) {
            $tags[] = 'fuera_his';
        }
        if (preg_match('/\b(representacion|representación|tutela|sobrin|sobrina|menor|representante)\b/u', mb_strtolower($normalized, 'UTF-8'))) {
            $tags[] = 'representacion';
        }

        if ($goal === 'operational') {
            $tags[] = 'tramite';
        }

        return array_values(array_unique($tags));
    }

    /**
     * Evita que la 1ª IA deje `sacar_turno` y `pedido_turno_sin_destino` a la vez.
     *
     * @param list<string> $tags
     * @return list<string>
     */
    private static function reconcileAgendaTags(array $tags, string $normalized): array
    {
        if ($normalized === '') {
            return $tags;
        }
        $folded = ChatChannelPolicy::fold($normalized);
        $hasDestino = self::hasAgendaDestino($folded);
        $isGestion = self::isGestionTurnoExistente($folded);
        $out = [];
        foreach ($tags as $tag) {
            if ($isGestion && ($tag === 'pedido_turno_sin_destino' || $tag === 'sacar_turno' || $tag === 'mis_turnos')) {
                continue;
            }
            if ($hasDestino && $tag === 'pedido_turno_sin_destino') {
                continue;
            }
            if (!$hasDestino && $tag === 'sacar_turno') {
                continue;
            }
            $out[] = $tag;
        }

        return array_values(array_unique($out));
    }

    private static function routingHintFromGoal(string $goal): string
    {
        if ($goal === 'operational' || $goal === 'in_flow_question') {
            return 'clara';
        }
        if ($goal === 'guide') {
            return 'incompletas';
        }

        return 'dudosa';
    }
}
