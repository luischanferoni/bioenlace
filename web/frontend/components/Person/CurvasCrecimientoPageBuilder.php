<?php

namespace frontend\components\Person;

use common\models\Person\Persona;

/**
 * View model de curvas de crecimiento (Plotly).
 */
final class CurvasCrecimientoPageBuilder
{
    /**
     * @param array<string, mixed> $pesoPc
     * @param array<string, string> $pesoLabels
     * @param array<string, mixed> $tallaPc
     * @param array<string, string> $tallaLabels
     * @param array<string, mixed> $pcefPc
     * @param array<string, string> $pcefLabels
     * @param array<string, mixed> $imcPc
     * @param array<string, string> $imcLabels
     * @param array<string, mixed>|false|null $datosCrecimiento
     * @return array{showImc: bool, charts: list<array<string, mixed>>}
     */
    public static function build(
        Persona $persona,
        array $pesoPc,
        array $pesoLabels,
        array $tallaPc,
        array $tallaLabels,
        array $pcefPc,
        array $pcefLabels,
        array $imcPc,
        array $imcLabels,
        $datosCrecimiento
    ): array {
        $datos = is_array($datosCrecimiento) ? $datosCrecimiento : [];
        $showImc = ((float) $persona->edadCrecimiento) > 1;

        $charts = [
            self::chart(
                'peso_edad',
                'Peso para la edad',
                'Peso',
                $pesoPc,
                $pesoLabels,
                self::jsonCol($datos['edad_atencion_y'] ?? null),
                self::jsonCol($datos['peso'] ?? null)
            ),
            self::chart(
                'talla_edad',
                'Talla para la edad',
                'Talla',
                $tallaPc,
                $tallaLabels,
                self::jsonCol($datos['edad_atencion_y'] ?? null),
                self::jsonCol($datos['talla'] ?? null)
            ),
            self::chart(
                'pcefalico_edad',
                'Perímetro cefalico para la edad',
                'Perímetro',
                $pcefPc,
                $pcefLabels,
                self::jsonCol($datos['edad_atencion_y'] ?? null),
                self::jsonCol($datos['perimetro_cefalico'] ?? null)
            ),
        ];

        if ($showImc) {
            $charts[] = self::chart(
                'imc_edad',
                'IMC para la edad',
                'IMC',
                $imcPc,
                $imcLabels,
                self::jsonCol($datos['edad_atencion_y'] ?? null),
                self::jsonCol($datos['imc'] ?? null)
            );
        }

        return [
            'showImc' => $showImc,
            'charts' => $charts,
        ];
    }

    /**
     * @param array<string, mixed> $pc
     * @param array<string, string> $labels
     * @param list<mixed> $personaX
     * @param list<mixed> $personaY
     * @return array<string, mixed>
     */
    private static function chart(
        string $elementId,
        string $title,
        string $yTitle,
        array $pc,
        array $labels,
        array $personaX,
        array $personaY
    ): array {
        $x = self::jsonCol($pc['edad_y'] ?? null);
        $traces = [];
        foreach ([1, 2, 3, 4, 5, 6, 7] as $n) {
            $key = 'P' . $n;
            $traces[] = [
                'x' => $x,
                'y' => self::jsonCol($pc[$key] ?? null),
                'name' => (string) ($labels[$key] ?? $key),
            ];
        }
        $traces[] = [
            'x' => $personaX,
            'y' => $personaY,
            'mode' => 'lines+markers',
            'name' => 'Atenciones',
            'line' => [
                'dash' => 'dashdot',
                'width' => 0.5,
                'color' => 'black',
            ],
        ];

        return [
            'elementId' => $elementId,
            'title' => $title,
            'yTitle' => $yTitle,
            'traces' => $traces,
        ];
    }

    /**
     * @param mixed $value
     * @return list<mixed>
     */
    private static function jsonCol($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values($decoded);
            }
        }

        return [];
    }
}
