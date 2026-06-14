<?php

namespace common\components\Platform\Assistant\Chat\Preprocess;

use Yii;
use common\components\Platform\Ai\IAManager;

/**
 * Preprocess: canal (user_goal), texto normalizado y extracciones (spans).
 */
final class ChatPreprocessService
{
    public const GOALS = [
        'operational',
        'conversational',
        'informational',
        'in_flow_question',
        'meta',
        'unclear',
    ];

    /**
     * Categorías de entidad permitidas en extracciones (acotado; ampliar según producto).
     *
     * @return list<string>
     */
    /**
     * Contenido clínico (síntomas, malestar) sin pedido operativo del sistema.
     */
    public static function isClinicalSymptomContent(string $content): bool
    {
        $lower = mb_strtolower(trim($content), 'UTF-8');
        if ($lower === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(problema|dolor|duele|síntoma|sintoma|malestar|enfermo|fiebre|tos|náusea|nausea|vómito|vomito|mareo|hinchazón|hinchazon|presión|presion|diabetes|hipertensión|hipertension|chichón|chichon|golpe|hematoma|moretón|moreton|bulto|hinchado|inflamado|cabeza|manos|pies|brazo|pierna|herida|sangra|sangrado)\b/u',
            $lower
        );
    }

    /**
     * Consultas staff vía DataAccess (/api/info, /api/listar): conteos, listados, resúmenes.
     */
    public static function isStaffDataAccessQuery(string $content): bool
    {
        $lower = self::foldAccents(mb_strtolower(trim($content), 'UTF-8'));
        if ($lower === '') {
            return false;
        }

        if (preg_match(
            '/\b(cuantos|numero de|total de|conteo de|cantidad de|resumen de|resumen)\b/u',
            $lower
        )) {
            return true;
        }

        if (preg_match(
            '/\b(listar|mostrar|mostrame|ver listado|nombres de|quienes|listado)\b/u',
            $lower
        )) {
            return true;
        }

        if (str_contains($lower, 'profesionales del centro')
            || str_contains($lower, 'medicos del centro')) {
            return true;
        }

        return false;
    }

    /**
     * Edición dispersa staff (/api/editar): modificar datos autorizados del centro.
     */
    public static function isStaffDataAccessEditQuery(string $content): bool
    {
        $lower = self::foldAccents(mb_strtolower(trim($content), 'UTF-8'));
        if ($lower === '') {
            return false;
        }

        if (!preg_match(
            '/\b(editar|modificar|actualizar|cambiar|corregir)\b/u',
            $lower
        )) {
            return false;
        }

        if (preg_match(
            '/\b(turno|turnos|cita|cancelar turno|reprogramar|sobreturno)\b/u',
            $lower
        )) {
            return false;
        }

        return true;
    }

    /**
     * Consultas o edición staff vía DataAccess (info / listar / editar).
     */
    public static function isStaffDataAccessOperationalQuery(string $content): bool
    {
        return self::isStaffDataAccessQuery($content) || self::isStaffDataAccessEditQuery($content);
    }

    private static function foldAccents(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    public static function allowedEntityCategories(): array
    {
        return [
            'servicio',
            'efector',
            'persona',
            'profesional',
            'turno',
        ];
    }

    /**
     * @return array{
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }
     */
    public static function run(string $content, int $userId): array
    {
        $content = trim($content);
        if ($content === '') {
            return self::emptyResult('');
        }

        $ia = self::runAi($content);
        if ($ia !== null) {
            return $ia;
        }

        return self::heuristicFallback($content);
    }

    /**
     * Bloque estable al inicio del prompt (context caching: instrucciones + esquema JSON).
     */
    public static function stablePromptPrefix(): string
    {
        $categories = json_encode(self::allowedEntityCategories(), JSON_UNESCAPED_UNICODE);
        $goals = json_encode(self::GOALS, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Analizá el mensaje del usuario para un asistente de salud.

Respondé ÚNICAMENTE con JSON:
{
  "normalized_text": "mensaje limpio, ortografía corregida y abreviaturas médicas abiertas cuando aplique",
  "user_goal": "uno de {$goals}",
  "action_text": "fragmento que expresa la acción pedida (turno, cancelar, etc.) o vacío",
  "extractions": [
    {
      "span": "fragmento mencionado (no palabras sueltas)",
      "category": "una de {$categories}",
      "synonyms": ["0-2 variantes ortográficas o abreviaturas"]
    }
  ]
}

Reglas:
- user_goal operational si pide hacer algo en el sistema (turno, agenda, cancelar), consulta datos operativos del centro (cuántos profesionales hay, planta profesional, listar médicos del efector) o modificar datos del personal (editar nombre, cambiar horarios de agenda del centro).
- conversational si es saludo, consulta de salud/síntomas, lesiones (golpe, chichón, bulto), malestar o charla sin pedir una acción del sistema (no confundir con informational).
- informational solo si pregunta qué puede hacer la app, pide ayuda/menú o lista de opciones; no usar informational para síntomas, quejas clínicas ni consultas de plantilla/conteo de profesionales del efector.
- No uses category servicio para síntomas, partes del cuerpo ni malestar; esas menciones van solo en normalized_text.
- meta: preguntas sobre el asistente o la app (no operativas).
- normalized_text: corregí ortografía y expandí abreviaturas clínicas comunes; conservá el sentido del mensaje.
- extractions: solo menciones de entidades del mundo (servicio, centro, persona), no verbos ni la acción.
- synonyms: máximo 2 strings por extracción.

Mensaje:
PROMPT;
    }

    public static function userMessagePart(string $content): string
    {
        return trim($content);
    }

    public static function buildFullPrompt(string $content): string
    {
        return self::stablePromptPrefix() . self::userMessagePart($content);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function runAi(string $content): ?array
    {
        $prompt = self::buildFullPrompt($content);

        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-preprocess', 'analysis');
            if (!is_array($raw)) {
                return null;
            }
            return self::normalizeResult($raw, $content);
        } catch (\Throwable $e) {
            Yii::warning('ChatPreprocessService IA: ' . $e->getMessage(), 'asistente');
            return null;
        }
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeResult(array $raw, string $fallbackContent): array
    {
        $goal = isset($raw['user_goal']) ? trim((string) $raw['user_goal']) : 'unclear';
        if (!in_array($goal, self::GOALS, true)) {
            $goal = 'unclear';
        }

        $normalized = isset($raw['normalized_text']) ? trim((string) $raw['normalized_text']) : '';
        if ($normalized === '') {
            $normalized = $fallbackContent;
        }

        $actionText = isset($raw['action_text']) ? trim((string) $raw['action_text']) : '';

        $allowedCat = array_flip(self::allowedEntityCategories());
        $extractions = [];
        if (isset($raw['extractions']) && is_array($raw['extractions'])) {
            foreach ($raw['extractions'] as $ex) {
                if (!is_array($ex)) {
                    continue;
                }
                $span = isset($ex['span']) ? trim((string) $ex['span']) : '';
                $cat = isset($ex['category']) ? trim((string) $ex['category']) : '';
                if ($span === '' || $cat === '' || !isset($allowedCat[$cat])) {
                    continue;
                }
                $syns = [];
                if (isset($ex['synonyms']) && is_array($ex['synonyms'])) {
                    foreach ($ex['synonyms'] as $s) {
                        if (is_string($s) && trim($s) !== '') {
                            $syns[] = trim($s);
                        }
                        if (count($syns) >= 2) {
                            break;
                        }
                    }
                }
                $extractions[] = [
                    'span' => $span,
                    'category' => $cat,
                    'synonyms' => $syns,
                ];
            }
        }

        $goal = self::applyClinicalGoalOverride($normalized, $goal);
        $goal = self::applyStaffOperationalGoalOverride($normalized, $goal);

        return [
            'normalized_text' => $normalized,
            'user_goal' => $goal,
            'action_text' => $actionText,
            'extractions' => $extractions,
        ];
    }

    private static function applyClinicalGoalOverride(string $normalized, string $goal): string
    {
        if (!self::isClinicalSymptomContent($normalized)) {
            return $goal;
        }

        if ($goal === 'informational' || $goal === 'unclear') {
            return 'conversational';
        }

        if ($goal === 'operational' && !preg_match('/\b(turno|turnos|reservar|sacar turno|cancelar|agenda|cita)\b/u', mb_strtolower($normalized, 'UTF-8'))) {
            return 'conversational';
        }

        return $goal;
    }

    private static function applyStaffOperationalGoalOverride(string $normalized, string $goal): string
    {
        if (!self::isStaffDataAccessOperationalQuery($normalized)) {
            return $goal;
        }

        return 'operational';
    }

    /**
     * @return array<string, mixed>
     */
    private static function heuristicFallback(string $content): array
    {
        $lower = mb_strtolower($content, 'UTF-8');
        $goal = 'unclear';
        if (preg_match('/\b(turno|turnos|reservar|sacar turno|cancelar turno|agenda|cita)\b/u', $lower)) {
            $goal = 'operational';
        } elseif (self::isStaffDataAccessOperationalQuery($content)) {
            $goal = 'operational';
        } elseif (preg_match('/\b(ayuda|qué puedo|que puedo|menu|menú|opciones|qué hace|que hace)\b/u', $lower)) {
            $goal = 'informational';
        } elseif (preg_match('/\b(hola|buenos|gracias|qué tal|como estas)\b/u', $lower)) {
            $goal = 'conversational';
        } elseif (self::isClinicalSymptomContent($content)) {
            $goal = 'conversational';
        }

        return [
            'normalized_text' => $content,
            'user_goal' => $goal,
            'action_text' => $goal === 'operational' ? $content : '',
            'extractions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyResult(string $content): array
    {
        return [
            'normalized_text' => $content,
            'user_goal' => 'unclear',
            'action_text' => '',
            'extractions' => [],
        ];
    }
}
