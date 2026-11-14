<?php

namespace common\components\Domain\Clinical\Specialty\Domain\Catalog;

/**
 * Instrumentos de screening en salud mental.
 *
 * Fuente única de los puntajes y de sus tramos de severidad. La severidad de un
 * puntaje se calcula desde acá; ningún consumidor la repite.
 */
final class MentalHealthInstrumentCatalog
{
    public const PHQ9 = 'phq9';
    public const GAD7 = 'gad7';

    /**
     * @return array<string, array{code: string, label: string, min: int, max: int, unit: string, bands: list<array{max: int|null, label: string, intent: string}>}>
     */
    public static function all(): array
    {
        return [
            self::PHQ9 => [
                'code' => self::PHQ9,
                'label' => 'PHQ-9',
                'min' => 0,
                'max' => 27,
                'unit' => 'pts',
                'bands' => [
                    ['max' => 4, 'label' => 'Mínima', 'intent' => 'success'],
                    ['max' => 9, 'label' => 'Leve', 'intent' => 'info'],
                    ['max' => 14, 'label' => 'Moderada', 'intent' => 'warning'],
                    ['max' => 19, 'label' => 'Moderadamente grave', 'intent' => 'danger'],
                    ['max' => null, 'label' => 'Grave', 'intent' => 'danger'],
                ],
            ],
            self::GAD7 => [
                'code' => self::GAD7,
                'label' => 'GAD-7',
                'min' => 0,
                'max' => 21,
                'unit' => 'pts',
                'bands' => [
                    ['max' => 4, 'label' => 'Mínima', 'intent' => 'success'],
                    ['max' => 9, 'label' => 'Leve', 'intent' => 'info'],
                    ['max' => 14, 'label' => 'Moderada', 'intent' => 'warning'],
                    ['max' => null, 'label' => 'Grave', 'intent' => 'danger'],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{code: string, label: string, min: int, max: int, unit: string, bands: list<array{max: int|null, label: string, intent: string}>}|null
     */
    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    public static function isValid(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    /**
     * Tramo de severidad para un puntaje, o `null` si el valor no es numérico o
     * queda fuera del rango del instrumento.
     *
     * @return array{label: string, intent: string}|null
     */
    public static function severityFor(string $code, $score): ?array
    {
        $instrument = self::get($code);
        if ($instrument === null || !is_numeric($score)) {
            return null;
        }

        $value = (float) $score;
        if ($value < $instrument['min'] || $value > $instrument['max']) {
            return null;
        }

        foreach ($instrument['bands'] as $band) {
            if ($band['max'] === null || $value <= $band['max']) {
                return ['label' => $band['label'], 'intent' => $band['intent']];
            }
        }

        return null;
    }
}
