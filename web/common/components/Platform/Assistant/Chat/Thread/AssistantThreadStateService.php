<?php

namespace common\components\Platform\Assistant\Chat\Thread;

use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusResolver;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\models\AsistenteConversacion;
use Yii;

/**
 * Hilos de dominio + certeza sobre la necesidad del usuario.
 * Estado en {@see AsistenteConversacion::$contexto_json}; tags por mensaje en metadata de interacción.
 */
final class AssistantThreadStateService
{
    private const BOT_ID = 'BOT';

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @var array<int, array{active_tag: string, confidence: float, hypothesis: string, updated_at?: string}> */
    private static array $memoryStates = [];

    public static function resetCacheForTests(): void
    {
        self::$config = null;
        self::$memoryStates = [];
        AssistantMetadataLoader::resetCacheForTests();
        AssistantThreadContext::clear();
    }

    /**
     * Limpia hilo en memoria y, si hay conversación, `thread` / `guide_focus` en BD.
     * Útil entre casos de smoke QA para arrancar “en frío”.
     */
    public static function clearPersistedStateForUser(int $userId): void
    {
        unset(self::$memoryStates[$userId]);
        AssistantThreadContext::clear();
        if ($userId <= 0) {
            return;
        }

        try {
            $conv = AsistenteConversacion::findOne([
                'usuario_id' => (string) $userId,
                'bot_id' => self::BOT_ID,
            ]);
        } catch (\Throwable $e) {
            return;
        }
        if ($conv === null) {
            return;
        }

        $ctx = self::decodeContexto($conv->contexto_json);
        unset($ctx['thread'], $ctx['guide_focus']);
        $conv->contexto_json = json_encode($ctx, JSON_UNESCAPED_UNICODE);
        $conv->updated_at = date('Y-m-d H:i:s');
        if (!$conv->save(false)) {
            Yii::warning('AssistantThreadStateService: no se pudo limpiar contexto_json', __METHOD__);
        }
    }

    public static function tagFromGoal(string $goal): string
    {
        $goal = ChatPreprocessService::canonicalizeGoal($goal);
        $map = self::load()['goal_to_thread_tag'] ?? [];
        if (!is_array($map)) {
            return '';
        }
        $tag = trim((string) ($map[$goal] ?? ''));

        return $tag;
    }

    /**
     * Observa el mensaje, actualiza estado de conversación y fija {@see AssistantThreadContext}.
     * Si hay desvío de dominio, puede reescribir el goal a ambiguous.
     *
     * @return array{goal: string, thread_tag: string, clear_history: bool, offer_cta: bool}
     */
    public static function observe(int $userId, string $goal, string $content): array
    {
        $goal = ChatPreprocessService::canonicalizeGoal($goal);
        $incomingTag = self::tagFromGoal($goal);
        $guideFocusMeta = null;

        if ($goal === 'guide') {
            $focus = GuideFocusResolver::resolve(
                ChatPreprocessContext::contextAreas(),
                self::loadGuideFocus($userId),
                GuideFocusResolver::carryFocusEnabled()
            );
            $guideFocusMeta = $focus->toMetadataArray();
            $incomingTag = $focus->threadTag();
        }

        $state = self::loadPersistedState($userId);
        $previousTag = trim((string) ($state['active_tag'] ?? ''));
        $confidence = (float) ($state['confidence'] ?? 0.0);

        $diverted = self::isDiversion($previousTag, $incomingTag);
        $clearHistory = false;
        $offerCta = false;

        if ($diverted) {
            $clearHistory = true;
            $confidence = 0.0;
            if (
                self::shouldForceAmbiguous($incomingTag)
                && !ChatChannelPolicy::isAppointmentPolicyQuestion($content)
            ) {
                $goal = 'ambiguous';
                $incomingTag = self::tagFromGoal($goal);
            }
        }

        if ($incomingTag === 'guide' || str_starts_with($incomingTag, 'guide:')) {
            $confidence = self::updateGuideConfidence($confidence, $content, $diverted || $previousTag !== 'guide');
            $offerCta = $confidence >= self::ctaThreshold()
                || ChatChannelPolicy::isClinicalSymptomContent($content);
        } else {
            $confidence = 0.0;
            $offerCta = false;
        }

        $confidence = self::clampConfidence($confidence);

        self::savePersistedState($userId, [
            'active_tag' => $incomingTag,
            'confidence' => $confidence,
            'hypothesis' => self::hypothesisForTag($incomingTag),
            'updated_at' => date('c'),
        ], $guideFocusMeta);

        AssistantThreadContext::set([
            'thread_tag' => $incomingTag,
            'previous_tag' => $previousTag,
            'diverted' => $diverted,
            'confidence' => $confidence,
            'offer_cta' => $offerCta,
            'clear_history' => $clearHistory,
            'guide_focus' => $guideFocusMeta,
        ]);

        return [
            'goal' => $goal,
            'thread_tag' => $incomingTag,
            'clear_history' => $clearHistory,
            'offer_cta' => $offerCta,
        ];
    }

    /**
     * @return array{thread_tag: string}
     */
    public static function metadataForPersistence(): array
    {
        $meta = [];
        $tag = AssistantThreadContext::threadTag();
        if ($tag !== '') {
            $meta['thread_tag'] = $tag;
        }
        $focus = AssistantThreadContext::guideFocus();
        if ($focus !== null) {
            $meta['guide_focus'] = $focus;
        }

        return $meta;
    }

    /**
     * @param mixed $raw
     * @return array{primary_area: string, active_areas: list<string>}|null
     */
    public static function guideFocusFromMetadata($raw): ?array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return null;
        }
        $focus = $raw['guide_focus'] ?? null;
        if (!is_array($focus)) {
            return null;
        }
        $state = GuideFocusState::fromMetadataArray($focus);

        return $state !== null ? $state->toMetadataArray() : null;
    }

    /**
     * @param mixed $raw
     */
    public static function threadTagFromMetadata($raw): string
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return '';
        }

        return trim((string) ($raw['thread_tag'] ?? ''));
    }

    private static function isDiversion(string $previousTag, string $incomingTag): bool
    {
        if ($incomingTag === '' || $previousTag === '' || $previousTag === $incomingTag) {
            return false;
        }
        $ignoreFrom = self::stringList(self::load()['diversion']['ignore_from_tags'] ?? ['ambiguous', '']);
        if (in_array($previousTag, $ignoreFrom, true)) {
            return false;
        }

        return true;
    }

    private static function shouldForceAmbiguous(string $incomingTag): bool
    {
        $cfg = self::load()['diversion'] ?? [];
        if (empty($cfg['force_ambiguous'])) {
            return false;
        }
        $ignoreTo = self::stringList($cfg['ignore_to_tags'] ?? ['ambiguous', 'operational']);

        return !in_array($incomingTag, $ignoreTo, true);
    }

    private static function updateGuideConfidence(float $current, string $content, bool $newThread): float
    {
        $cfg = self::load()['certainty'] ?? [];
        if ($newThread) {
            $current = 0.0;
        }
        if (ChatChannelPolicy::isClinicalSymptomContent($content)) {
            $symptom = (float) ($cfg['symptom_confidence'] ?? 0.85);

            return max($current, $symptom);
        }
        // Saludo puro: no acumula certeza hacia el CTA.
        if (ChatChannelPolicy::isGreetingOnly($content)) {
            return $current;
        }

        $delta = (float) ($cfg['followup_delta'] ?? 0.15);

        return $current + $delta;
    }

    private static function ctaThreshold(): float
    {
        return (float) (self::load()['certainty']['cta_threshold'] ?? 0.7);
    }

    private static function clampConfidence(float $value): float
    {
        $cfg = self::load()['certainty'] ?? [];
        $min = (float) ($cfg['confidence_min'] ?? 0.0);
        $max = (float) ($cfg['confidence_max'] ?? 1.0);

        return max($min, min($max, $value));
    }

    private static function hypothesisForTag(string $tag): string
    {
        if (str_starts_with($tag, 'guide')) {
            return 'guia_his';
        }

        return match ($tag) {
            'operational' => 'tramite',
            'ambiguous' => 'por_definir',
            default => '',
        };
    }

    /**
     * @return array{active_tag: string, confidence: float, hypothesis: string, updated_at?: string}
     */
    private static function loadPersistedState(int $userId): array
    {
        $defaults = [
            'active_tag' => '',
            'confidence' => 0.0,
            'hypothesis' => '',
        ];
        if ($userId <= 0) {
            return self::$memoryStates[$userId] ?? $defaults;
        }

        if (isset(self::$memoryStates[$userId])) {
            return self::$memoryStates[$userId];
        }

        try {
            $conv = AsistenteConversacion::findOne([
                'usuario_id' => (string) $userId,
                'bot_id' => self::BOT_ID,
            ]);
        } catch (\Throwable $e) {
            return $defaults;
        }
        if ($conv === null) {
            return $defaults;
        }

        $ctx = self::decodeContexto($conv->contexto_json);
        $thread = is_array($ctx['thread'] ?? null) ? $ctx['thread'] : [];

        return [
            'active_tag' => trim((string) ($thread['active_tag'] ?? '')),
            'confidence' => (float) ($thread['confidence'] ?? 0.0),
            'hypothesis' => trim((string) ($thread['hypothesis'] ?? '')),
            'updated_at' => (string) ($thread['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array{active_tag: string, confidence: float, hypothesis: string, updated_at?: string} $thread
     * @param array{primary_area: string, active_areas: list<string>}|null $guideFocus
     */
    private static function savePersistedState(int $userId, array $thread, ?array $guideFocus = null): void
    {
        self::$memoryStates[$userId] = $thread;
        if ($userId <= 0) {
            return;
        }

        try {
            $conv = AsistenteConversacion::findOne([
                'usuario_id' => (string) $userId,
                'bot_id' => self::BOT_ID,
            ]);
        } catch (\Throwable $e) {
            return;
        }
        if ($conv === null) {
            return;
        }

        $ctx = self::decodeContexto($conv->contexto_json);
        $ctx['thread'] = $thread;
        if ($guideFocus !== null) {
            $ctx['guide_focus'] = $guideFocus;
        }
        $conv->contexto_json = json_encode($ctx, JSON_UNESCAPED_UNICODE);
        $conv->updated_at = date('Y-m-d H:i:s');
        if (!$conv->save(false)) {
            Yii::warning('AssistantThreadStateService: no se pudo guardar contexto_json', __METHOD__);
        }
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private static function decodeContexto($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @return array{primary_area: string, active_areas: list<string>}|null
     */
    private static function loadGuideFocus(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $conv = AsistenteConversacion::findOne([
                'usuario_id' => (string) $userId,
                'bot_id' => self::BOT_ID,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
        if ($conv === null) {
            return null;
        }

        $ctx = self::decodeContexto($conv->contexto_json);
        $focus = $ctx['guide_focus'] ?? null;
        if (!is_array($focus)) {
            return null;
        }

        $state = GuideFocusState::fromMetadataArray($focus);

        return $state !== null ? $state->toMetadataArray() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        self::$config = AssistantMetadataLoader::load(ProductMetadataPaths::threadStateFile());

        return self::$config;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function stringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) || is_int($item)) {
                $out[] = trim((string) $item);
            }
        }

        return $out;
    }
}
