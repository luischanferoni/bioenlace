<?php

namespace common\components\Domain\Clinical\Capture\Application\Service;

use common\helpers\TextoMedicoHelper;

/**
 * Application Service: limpieza local (CPU) del texto clínico antes de la IA.
 * Ortografía/abreviaturas finas las devuelve la extracción en `texto_procesado`.
 */
class CaptureTextService
{
    /**
     * Solo limpieza local; la corrección de la nota la entrega la extracción IA.
     *
     * @return array{texto_procesado: string}
     */
    public static function prepararParaIA(string $texto, ?string $nombreServicio = null, ?string $tabId = null): array
    {
        return ['texto_procesado' => self::pipelineTextoPlano($texto)];
    }

    /**
     * Igual que prepararParaIA; `texto_formateado` es el texto limpio escapado (sin subrayado local).
     *
     * @return array{texto_procesado: string, texto_formateado: string, total_cambios: int}
     */
    public static function prepararParaIAConFormato(
        string $texto,
        ?string $nombreServicio = null,
        ?string $tabId = null,
        ?int $idRrHhServicio = null
    ): array {
        $limp = self::pipelineTextoPlano($texto);

        return [
            'texto_procesado' => $limp,
            'texto_formateado' => htmlspecialchars($limp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'total_cambios' => 0,
        ];
    }

    private static function pipelineTextoPlano(string $texto): string
    {
        return TextoMedicoHelper::limpiarTexto($texto);
    }
}
