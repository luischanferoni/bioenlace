<?php

namespace frontend\components\Clinical;

/**
 * View model de la receta de lentes para la historia clínica.
 *
 * Traduce las medidas por ojo a filas listas para pintar.
 */
final class RecetaLentesViewBuilder
{
    /** @var list<array{key: string, label: string}> */
    private const FILAS = [
        ['key' => 'esfera', 'label' => 'Esfera'],
        ['key' => 'cilindro', 'label' => 'Cilindro'],
        ['key' => 'eje', 'label' => 'Eje'],
        ['key' => 'adicion', 'label' => 'Adición'],
    ];

    /**
     * @param array<string, mixed>|null $receta salida de `latestLensPrescriptionForPerson`
     * @return array{visible: bool, registradoEn: string, rows: list<array{label: string, od: string, oi: string}>}
     */
    public static function build(?array $receta): array
    {
        if (!is_array($receta)) {
            return ['visible' => false, 'registradoEn' => '', 'rows' => []];
        }

        $od = is_array($receta['od'] ?? null) ? $receta['od'] : [];
        $oi = is_array($receta['oi'] ?? null) ? $receta['oi'] : [];

        $rows = [];
        $hasValue = false;
        foreach (self::FILAS as $fila) {
            $valueOd = trim((string) ($od[$fila['key']] ?? ''));
            $valueOi = trim((string) ($oi[$fila['key']] ?? ''));
            if ($valueOd !== '' || $valueOi !== '') {
                $hasValue = true;
            }
            $rows[] = [
                'label' => $fila['label'],
                'od' => $valueOd,
                'oi' => $valueOi,
            ];
        }

        $registradoEn = trim((string) ($receta['registradoEn'] ?? ''));

        return [
            'visible' => $hasValue,
            'registradoEn' => $registradoEn !== '' ? substr($registradoEn, 0, 10) : '',
            'rows' => $rows,
        ];
    }
}
