<?php

namespace common\components\Organization\Service\ProfesionalEfectorServicio;

use common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext;
use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Organization\Service\Servicios\ServicioMencionLookupService;

/**
 * Enriquece draft del intent organization.resumen-profesionales-efector desde preprocess / contenido.
 *
 * @param array<string, mixed> $body
 * @param array<string, mixed> $options
 */
final class ProfesionalEfectorResumenFlowDraftHydrator
{
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $content = trim((string) ($body['content'] ?? ''));

        if (!isset($draft['servicio_rol']) || trim((string) $draft['servicio_rol']) === '') {
            $mention = self::servicioMentionFromExtractions();
            if ($mention === null && $content !== '') {
                $mention = $content;
            }
            if ($mention !== null && $mention !== '') {
                $ids = (new ServicioMencionLookupService())->idsDesdeMencion($mention);
                if ($ids !== []) {
                    $draft['servicio_rol'] = $mention;
                    $draft['servicio_rol_mention'] = $mention;
                }
            }
        }

        if (!isset($draft['sexo_biologico']) || $draft['sexo_biologico'] === '') {
            $sexoMention = self::sexoMentionFromExtractions($content);
            if ($sexoMention !== null) {
                $code = (new AttributeGroupCatalog())->resolveSexoBiologicoFromMention($sexoMention);
                if ($code !== null) {
                    $draft['sexo_biologico'] = (string) $code;
                }
            }
        }

        $body['draft'] = $draft;
    }

    private static function servicioMentionFromExtractions(): ?string
    {
        foreach (ChatPreprocessContext::extractions() as $ex) {
            if (!is_array($ex)) {
                continue;
            }
            if (trim((string) ($ex['category'] ?? '')) !== 'servicio') {
                continue;
            }
            $span = trim((string) ($ex['span'] ?? ''));

            return $span !== '' ? $span : null;
        }

        return null;
    }

    private static function sexoMentionFromExtractions(string $content): ?string
    {
        foreach (ChatPreprocessContext::extractions() as $ex) {
            if (!is_array($ex)) {
                continue;
            }
            $cat = trim((string) ($ex['category'] ?? ''));
            if ($cat !== 'persona' && $cat !== 'profesional') {
                continue;
            }
            $span = trim((string) ($ex['span'] ?? ''));
            if ($span !== '') {
                return $span;
            }
        }

        if ($content !== '') {
            return $content;
        }

        return null;
    }
}
