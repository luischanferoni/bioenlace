<?php

namespace common\components\Platform\Assistant\Chat\Preprocess;

use Yii;
use common\components\Ai\IAManager;

/**
 * Preprocess: canal (user_goal), texto normalizado y extracciones (spans).
 *
 * El `user_goal` lo decide la IA; no hay piso PHP ni fallback heurístico si la IA falla.
 * Predicados de dominio (síntoma, staff data-access): {@see ChatChannelPolicy}.
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

    private const ENTITY_CATEGORIES = ['servicio', 'efector', 'persona', 'profesional', 'turno'];

    public static function isClinicalSymptomContent(string $content): bool
    {
        return ChatChannelPolicy::isClinicalSymptomContent($content);
    }

    public static function isStaffDataAccessQuery(string $content): bool
    {
        return ChatChannelPolicy::isStaffDataAccessQuery($content);
    }

    public static function isStaffDataAccessEditQuery(string $content): bool
    {
        return ChatChannelPolicy::isStaffDataAccessEditQuery($content);
    }

    public static function isStaffDataAccessOperationalQuery(string $content): bool
    {
        return ChatChannelPolicy::isStaffDataAccessOperationalQuery($content);
    }

    /**
     * @return list<string>
     */
    public static function allowedEntityCategories(): array
    {
        return self::ENTITY_CATEGORIES;
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }|null null = falló la IA (sin clasificar por heurística)
     */
    public static function run(string $content, int $userId): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return self::emptyResult('');
        }

        return self::runAi($content);
    }

    public static function stablePromptPrefix(): string
    {
        $categoriesList = self::allowedEntityCategories();
        $categories = json_encode($categoriesList, JSON_UNESCAPED_UNICODE);
        $goals = json_encode(self::GOALS, JSON_UNESCAPED_UNICODE);
        $categoriesHuman = implode(', ', $categoriesList);

        return <<<PROMPT
Clasificá el mensaje del usuario para el asistente de un HIS (sistema de historia clínica y gestión de salud).

Alcance válido (solo esto entra a un canal):
- Salud, síntomas o malestar del paciente autenticado.
- Gestiones en Bioenlace sobre sí mismo (turnos, estudios, controles, representación/tutela formal, cuestionarios, lectura de lo propio).
- Ayuda sobre cómo funciona el producto para hacer esas gestiones.
- Preguntas sobre un flujo ya abierto o sobre el asistente.

Respondé ÚNICAMENTE con JSON:
{
  "normalized_text": "mensaje limpio, ortografía corregida y abreviaturas médicas abiertas cuando aplique",
  "user_goal": "uno de {$goals}",
  "action_text": "fragmento que expresa la acción pedida o vacío",
  "extractions": [
    {
      "span": "fragmento mencionado (no palabras sueltas)",
      "category": "una de {$categories}",
      "synonyms": ["0-2 variantes ortográficas o abreviaturas"]
    }
  ]
}

Reglas de user_goal (elige uno; debe encajar en el alcance válido):
- operational: ejecutar o consultar un trámite concreto en el sistema.
- conversational: saludo, o charla sobre su salud/malestar sin trámite concreto.
- informational: menú o cómo funciona el producto para gestiones del alcance (aunque mencione "turno" si solo pregunta).
- in_flow_question: pregunta sobre un flujo ya en curso.
- meta: pregunta sobre el asistente mismo.
- unclear: el mensaje no encaja con claridad en ninguno de los anteriores o no está en el alcance válido.

Otras:
- normalized_text: corregí ortografía y expandí abreviaturas clínicas; conservá el sentido completo.
- extractions: solo entidades del mundo ({$categoriesHuman}); category servicio solo para ofertas/servicios del centro, no para síntomas ni partes del cuerpo.
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
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }|null
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
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }
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

        return [
            'ok' => true,
            'normalized_text' => $normalized,
            'user_goal' => $goal,
            'action_text' => $actionText,
            'extractions' => $extractions,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }
     */
    private static function emptyResult(string $content): array
    {
        return [
            'ok' => true,
            'normalized_text' => $content,
            'user_goal' => 'unclear',
            'action_text' => '',
            'extractions' => [],
        ];
    }
}
