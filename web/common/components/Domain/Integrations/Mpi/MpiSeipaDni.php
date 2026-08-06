<?php

namespace common\components\Domain\Integrations\Mpi;

/**
 * SEIPA (coberturas / renaper / domicilio) tipa `dni` como Long Java.
 * Documentos con letras (p. ej. extranjero histórico `X8673354`) provocan 500 en el remoto
 * envuelto en HTTP 200 — hay que no llamar.
 */
final class MpiSeipaDni
{
    /**
     * DNI aceptable para query SEIPA (`Long`), o null si no conviene llamar.
     */
    public static function toLongQueryParam(?string $documento): ?string
    {
        $raw = trim((string) $documento);
        if ($raw === '') {
            return null;
        }

        // Solo dígitos: el gateway Spring no acepta letras ni prefijos.
        if (!ctype_digit($raw)) {
            return null;
        }

        // Long razonable para DNI/CUIT cortos usados en estos endpoints (no CUIT 11).
        $len = strlen($raw);
        if ($len < 7 || $len > 10) {
            return null;
        }

        // Evitar overflow de Long por ceros a la izquierda absurdos; normalizar sin perder valor.
        $normalized = ltrim($raw, '0');
        if ($normalized === '') {
            return null;
        }

        return $normalized;
    }
}
