<?php

namespace common\components\Text;

use common\helpers\TextoMedicoHelper;
use common\models\AbreviaturasMedicas;
use common\components\Text\SymSpellCorrector;

/**
 * Pipeline local (CPU) para normalizar texto clínico antes de enviarlo a la IA.
 */
class ProcesadorTextoMedico
{
    /**
     * Limpieza + corrección ortográfica + expansión de abreviaturas.
     *
     * @param string $texto
     * @param string|null $nombreServicio
     * @param string|null $tabId contexto de pestaña (reservado)
     * @return array{texto_procesado: string}
     */
    public static function prepararParaIA(string $texto, ?string $nombreServicio = null, ?string $tabId = null): array
    {
        $procesado = self::pipelineTextoPlano($texto, $nombreServicio ?? '');
        return ['texto_procesado' => $procesado];
    }

    /**
     * Igual que prepararParaIA pero devuelve HTML con subrayado en correcciones ortográficas
     * y conteo de cambios (ortografía; las abreviaturas se aplican después y no se subrayan aquí).
     *
     * @param string $texto
     * @param string|null $nombreServicio
     * @param string|null $tabId
     * @param int|null $idRrHhServicio reservado para contexto futuro
     * @return array{texto_procesado: string, texto_formateado: string, total_cambios: int}
     */
    public static function prepararParaIAConFormato(
        string $texto,
        ?string $nombreServicio = null,
        ?string $tabId = null,
        ?int $idRrHhServicio = null
    ): array {
        $servicio = $nombreServicio ?? '';
        $limp = TextoMedicoHelper::limpiarTexto($texto);
        $corrector = new SymSpellCorrector();
        $spell = $corrector->correctText($limp, $servicio);

        $trasOrtografia = $spell['corrected_text'] ?? $limp;
        $cambiosOrtografia = (int) ($spell['total_changes'] ?? 0);

        $textoProcesado = AbreviaturasMedicas::expandirAbreviaturas($trasOrtografia, $servicio ?: null);

        $textoFormateado = self::aplicarSubrayadoCorrecciones($trasOrtografia, $spell['changes'] ?? []);

        return [
            'texto_procesado' => $textoProcesado,
            'texto_formateado' => $textoFormateado,
            'total_cambios' => $cambiosOrtografia,
        ];
    }

    private static function pipelineTextoPlano(string $texto, string $servicio): string
    {
        $limp = TextoMedicoHelper::limpiarTexto($texto);
        $corrector = new SymSpellCorrector();
        $spell = $corrector->correctText($limp, $servicio);
        $trasOrtografia = $spell['corrected_text'] ?? $limp;
        return AbreviaturasMedicas::expandirAbreviaturas($trasOrtografia, $servicio ?: null);
    }

    /**
     * Marca en HTML (seguro) las palabras que fueron corregidas ortográficamente.
     *
     * @param array<int, array{original?: string, corrected?: string}> $changes
     */
    private static function aplicarSubrayadoCorrecciones(string $textoTrasOrtografia, array $changes): string
    {
        if ($changes === []) {
            return htmlspecialchars($textoTrasOrtografia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $t = $textoTrasOrtografia;
        $placeholders = [];
        $i = 0;
        foreach ($changes as $c) {
            $corr = $c['corrected'] ?? '';
            $orig = $c['original'] ?? '';
            if ($corr === '' || $corr === $orig) {
                continue;
            }
            $ph = '##U' . ($i++) . '##';
            $nt = @preg_replace('/\b' . preg_quote($corr, '/') . '\b/u', $ph, $t, 1);
            if ($nt !== null && $nt !== $t) {
                $placeholders[$ph] = '<u>' . htmlspecialchars($corr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</u>';
                $t = $nt;
            }
        }
        $t = htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        foreach ($placeholders as $ph => $html) {
            $t = str_replace($ph, $html, $t);
        }
        return $t;
    }
}
