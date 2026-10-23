<?php

namespace common\components\Domain\Clinical\Capture\Application\Service;

use common\components\Platform\Core\Http\Contract\ApiException;

/**
 * Une el texto de varias notas de un mismo borrador en un único texto para
 * `capture-extraction`, conservando el orden y marcando cada nota.
 */
final class CaptureBatchTextComposer
{
    public const MAX_NOTES = 10;
    public const MAX_CHARS = 8000;

    /**
     * @param list<string> $transcripts En orden cronológico.
     * @throws ApiException
     */
    public static function compose(array $transcripts): string
    {
        $notes = [];
        foreach ($transcripts as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                throw new ApiException('capture_batch_note_not_ready');
            }
            $notes[] = $text;
        }
        if ($notes === []) {
            throw new ApiException('capture_no_transcript');
        }
        if (count($notes) > self::MAX_NOTES) {
            throw new ApiException('capture_batch_too_many', ['detail' => (string) self::MAX_NOTES]);
        }
        if (count($notes) === 1) {
            return $notes[0];
        }

        $parts = [];
        foreach ($notes as $i => $text) {
            $parts[] = '[Nota ' . ($i + 1) . '] ' . $text;
        }
        $composed = implode("\n\n", $parts);
        if (mb_strlen($composed) > self::MAX_CHARS) {
            throw new ApiException('capture_batch_too_long', ['detail' => (string) self::MAX_CHARS]);
        }

        return $composed;
    }

    /**
     * Ids de captura del lote, sin repetidos y conservando el orden.
     *
     * @param array<string, mixed> $body
     * @return list<string>
     */
    public static function clientIdsFromBody(array $body): array
    {
        $raw = $body['batch_client_capture_ids'] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $id = trim((string) $id);
            if ($id !== '' && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
