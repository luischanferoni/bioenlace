<?php

namespace common\components\Platform\Assistant\Chat\Channels\Informational;

use common\components\Domain\Content\Service\InfoContentAssistantService;
use common\components\Platform\Assistant\Chat\Channels\Ambiguous\AmbiguousChannel;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\IntentEngine\IntentEngine;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;

/**
 * Canal informativo / meta: listar capacidades, contenido editorial o mensaje guía.
 * No cae al canal clínico: sin artículo → menú, mensaje corto o ambiguous.
 */
final class InformationalChannel
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(string $content, int $userId): array
    {
        if (IntentEngine::isListAllQueryPublic($content)) {
            return self::finalize(IntentEngine::processQuery($content, $userId, null));
        }

        $infoArticle = InfoContentAssistantService::tryResolveFromText(
            $content,
            $userId,
            self::currentIdEfector(),
            null
        );
        if ($infoArticle !== null) {
            return $infoArticle;
        }

        if (self::isCapabilityMenuQuery($content)) {
            return self::capabilityMenu($userId);
        }

        $goal = ChatPreprocessContext::userGoal();
        if ($goal === 'meta') {
            return AssistantEnvelope::message(
                InformationalChannelConfig::message(
                    'meta_intro',
                    'Soy el asistente de Bioenlace. Contame qué necesitás.'
                )
            );
        }

        // Ayuda de producto sin artículo: no mezclar con charla clínica.
        if (trim($content) === '') {
            return AmbiguousChannel::handle();
        }

        return AssistantEnvelope::message(
            InformationalChannelConfig::message(
                'no_article',
                'No encontré una guía sobre eso. Reformulá la pregunta o pedime el trámite concreto.'
            )
        );
    }

    /**
     * Pregunta explícita por capacidades/menú (no síntomas ni charla clínica).
     */
    public static function isCapabilityMenuQuery(string $content): bool
    {
        return ChatChannelPolicy::isCapabilityMenuQuery($content);
    }

    /**
     * @return array<string, mixed>
     */
    private static function capabilityMenu(int $userId): array
    {
        $catalog = UiActionCatalog::forUser($userId);
        $buttons = [];
        foreach (array_slice($catalog->items, 0, 8) as $it) {
            $buttons[] = [
                'label' => $it->display_name !== '' ? $it->display_name : $it->action_id,
                'intent_id' => $it->action_id,
            ];
        }

        return AssistantEnvelope::interactive(
            InformationalChannelConfig::message(
                'capability_menu_intro',
                'Estas son algunas cosas que podés hacer. Elegí una opción o contame qué necesitás.'
            ),
            $buttons
        );
    }

    private static function currentIdEfector(): ?int
    {
        try {
            $id = \Yii::$app->user->getIdEfector();

            return $id > 0 ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $motor
     * @return array<string, mixed>
     */
    private static function finalize(array $motor): array
    {
        if (empty($motor['success'])) {
            return $motor;
        }

        return AssistantEnvelope::fromMotorResponse($motor);
    }
}
