<?php

namespace common\components\Domain\Organization\Service\Servicios;

use common\models\Servicio;

/**
 * Resuelve menciones de texto libre → filas de {@see Servicio} (sin capa rol/YAML).
 */
final class ServicioMencionLookupService
{
    /**
     * @return list<int>
     */
    public function idsDesdeMencion(string $text): array
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if ($text === '') {
            return [];
        }

        if (ctype_digit($text)) {
            $id = (int) $text;

            return $id > 0 ? [$id] : [];
        }

        $tokens = preg_split('/[\s_]+/u', str_replace('_', ' ', $text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach (Servicio::find()->where(['acepta_turnos' => 'SI'])->all() as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            $nombre = mb_strtolower(trim((string) $servicio->nombre), 'UTF-8');
            if ($nombre === '') {
                continue;
            }
            if ($this->coincideNombre($nombre, $text, $tokens)) {
                $out[] = (int) $servicio->id_servicio;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $ids
     */
    public function labelParaIds(array $ids): string
    {
        if ($ids === []) {
            return '';
        }
        $nombres = [];
        foreach (Servicio::find()->where(['id_servicio' => $ids])->orderBy(['nombre' => SORT_ASC])->all() as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            $nombre = trim((string) $servicio->nombre);
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        return implode(', ', array_values(array_unique($nombres)));
    }

    /**
     * @param list<string> $tokens
     */
    private function coincideNombre(string $nombre, string $textoCompleto, array $tokens): bool
    {
        if (str_contains($nombre, $textoCompleto)) {
            return true;
        }
        $normalizado = str_replace('_', ' ', $textoCompleto);
        if ($normalizado !== $textoCompleto && str_contains($nombre, $normalizado)) {
            return true;
        }
        if ($tokens === []) {
            return false;
        }
        $hits = 0;
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || mb_strlen($token) < 3) {
                continue;
            }
            if (str_contains($nombre, $token)) {
                ++$hits;
            }
        }

        return $hits >= min(2, count(array_filter($tokens, static fn ($t) => mb_strlen(trim($t)) >= 3)));
    }
}
