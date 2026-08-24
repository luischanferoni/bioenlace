<?php

namespace common\components\Platform\Assistant\Chat\Preprocess;

use Yii;
use common\components\Ai\IAManager;

/**
 * Preprocess: canal (user_goal), texto normalizado y extracciones (spans).
 *
 * Política de canal: {@see ChatChannelPolicy}. Copy conversacional: otro YAML.
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
  "action_text": "fragmento que expresa la acción pedida o vacío",
  "extractions": [
    {
      "span": "fragmento mencionado (no palabras sueltas)",
      "category": "una de {$categories}",
      "synonyms": ["0-2 variantes ortográficas o abreviaturas"]
    }
  ]
}

Reglas:
- user_goal operational si pide una acción del sistema (turno, agenda, estudio/práctica concreto) o consulta datos propios resolubles.
- conversational si hay saludo, síntomas, lesiones o charla clínica (aunque también pregunten hospital cerca). Pedir un estudio/práctica concreto SÍ es operational.
- informational si pregunta qué puede hacer la app, menú/ayuda o cómo funciona algo del sistema.
- No uses category servicio para síntomas ni partes del cuerpo.
- extractions: solo entidades del mundo (servicio, centro, persona), no verbos.
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

        $goal = ChatChannelPolicy::resolveUserGoal($normalized, $goal, $fallbackContent);

        return [
            'normalized_text' => $normalized,
            'user_goal' => $goal,
            'action_text' => $actionText,
            'extractions' => $extractions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function heuristicFallback(string $content): array
    {
        $goal = ChatChannelPolicy::heuristicUserGoal($content);

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
