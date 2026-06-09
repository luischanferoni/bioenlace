<?php

namespace common\components\Clinical\CareCohort\Service;

/**
 * Detecta si las respuestas del paciente requieren adaptación del pack (delta IA).
 */
final class CareAssistanceDeltaEvaluator
{
    /** @var list<string> */
    private const URGENT_PATTERNS = [
        'empeor',
        'dolor fuerte',
        'dolor intenso',
        'no puedo respirar',
        'dificultad para respirar',
        'sangre',
        'sangrado',
        'desmay',
        'fiebre alta',
        'convulsion',
        'perd[ií] de conocimiento',
    ];

    /**
     * @param array<string, mixed> $answers
     * @param array<string, mixed> $packContent
     */
    public function needsAdaptation(array $answers, array $packContent): bool
    {
        foreach ($answers as $key => $value) {
            $text = mb_strtolower(trim((string) $value));
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > 200) {
                return true;
            }
            foreach (self::URGENT_PATTERNS as $pattern) {
                if (preg_match('/' . $pattern . '/u', $text)) {
                    return true;
                }
            }
            if ($this->isHighScaleAnswer($key, $text, $packContent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $packContent
     */
    private function isHighScaleAnswer(string $questionId, string $value, array $packContent): bool
    {
        if (!preg_match('/dolor|malestar|intensidad/u', $questionId)) {
            return false;
        }
        if (!is_numeric($value)) {
            return false;
        }

        return (int) $value >= 8;
    }
}
