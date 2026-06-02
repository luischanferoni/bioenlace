<?php

namespace common\components\Ai\SpeechToText;

use Yii;

/**
 * Heurísticas de calidad para transcripción en dispositivo (sin llamada STT en servidor).
 */
final class DeviceSttQualityAssessor
{
    /**
     * @param array<string, mixed> $meta confidence, duration_ms, locale, engine, client_edit_ratio
     * @return array{ok: bool, needs_server: bool, reasons: list<string>}
     */
    public static function assess(string $text, array $meta = [], ?string $flowProfile = null): array
    {
        $cfg = self::config($flowProfile);
        $reasons = [];
        $trimmed = trim($text);

        if (mb_strlen($trimmed) < (int) ($cfg['min_chars'] ?? 3)) {
            $reasons[] = 'texto_muy_corto';
        }

        $confidence = isset($meta['confidence']) ? (float) $meta['confidence'] : null;
        $minConf = (float) ($cfg['min_confidence'] ?? 0.75);
        if ($confidence !== null && $confidence > 0 && $confidence < $minConf) {
            $reasons[] = 'confianza_baja';
        }

        $durationMs = isset($meta['duration_ms']) ? (int) $meta['duration_ms'] : 0;
        if ($durationMs > 0) {
            $minutes = max($durationMs / 60000, 0.01);
            $wordCount = self::wordCount($trimmed);
            $wpm = $wordCount / $minutes;
            if ($wpm < (float) ($cfg['min_words_per_minute'] ?? 20)) {
                $reasons[] = 'pocas_palabras_para_duracion';
            }
        }

        if (self::fillerRatio($trimmed) > (float) ($cfg['max_filler_ratio'] ?? 0.7)) {
            $reasons[] = 'exceso_muletillas';
        }

        if (self::nonAlphaRatio($trimmed) > (float) ($cfg['max_non_alpha_ratio'] ?? 0.5)) {
            $reasons[] = 'texto_no_alfabetico';
        }

        if (self::hasExcessiveRepetition($trimmed)) {
            $reasons[] = 'repeticion_excesiva';
        }

        $locale = isset($meta['locale']) ? strtolower((string) $meta['locale']) : '';
        if ($locale !== '' && !str_starts_with($locale, 'es')) {
            $reasons[] = 'idioma_inesperado';
        }

        $editRatio = isset($meta['client_edit_ratio']) ? (float) $meta['client_edit_ratio'] : null;
        if ($editRatio !== null && $editRatio > (float) ($cfg['max_client_edit_ratio'] ?? 0.35)) {
            $reasons[] = 'edicion_alta_en_cliente';
        }

        $needsServer = $reasons !== [];
        $ok = !$needsServer;

        return [
            'ok' => $ok,
            'needs_server' => $needsServer,
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(?string $flowProfile): array
    {
        $base = Yii::$app->params['stt_device'] ?? [];
        if (!is_array($base)) {
            $base = [];
        }
        $profiles = $base['profiles'] ?? [];
        if ($flowProfile !== null && isset($profiles[$flowProfile]) && is_array($profiles[$flowProfile])) {
            return array_merge($base, $profiles[$flowProfile]);
        }

        return $base;
    }

    private static function wordCount(string $text): int
    {
        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $parts ? count($parts) : 0;
    }

    private static function fillerRatio(string $text): float
    {
        $tokens = preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens) {
            return 0.0;
        }
        $fillers = ['eh', 'em', 'um', 'este', 'bueno', 'o sea', 'tipo'];
        $hits = 0;
        foreach ($tokens as $t) {
            if (in_array($t, $fillers, true)) {
                $hits++;
            }
        }

        return $hits / count($tokens);
    }

    private static function nonAlphaRatio(string $text): float
    {
        $len = mb_strlen($text);
        if ($len === 0) {
            return 1.0;
        }
        $letters = preg_match_all('/\p{L}/u', $text);

        return 1.0 - min(1.0, ($letters ?: 0) / $len);
    }

    private static function hasExcessiveRepetition(string $text): bool
    {
        $tokens = preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens || count($tokens) < 4) {
            return false;
        }
        $run = 1;
        $prev = $tokens[0];
        for ($i = 1, $n = count($tokens); $i < $n; $i++) {
            if ($tokens[$i] === $prev) {
                $run++;
                if ($run >= 4) {
                    return true;
                }
            } else {
                $run = 1;
                $prev = $tokens[$i];
            }
        }

        return false;
    }
}
