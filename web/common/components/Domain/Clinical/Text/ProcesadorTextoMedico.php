<?php

namespace common\components\Domain\Clinical\Text;

use common\helpers\TextoMedicoHelper;

/**
 * Pipeline local (CPU) para normalizar texto clínico antes de enviarlo a la IA.
 * Ortografía, abreviaturas y formato fino los devuelve la IA en `texto_procesado`
 * (ver clinical-text-ia.yaml → encounter_capture_extraction). SymSpell no se usa.
 */
class ProcesadorTextoMedico
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
