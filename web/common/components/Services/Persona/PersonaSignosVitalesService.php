<?php

namespace common\components\Services\Persona;

use common\models\Persona;
use common\models\PersonaRepository;

/**
 * Orquesta la lectura de signos vitales desde atenciones de enfermería (JSON en ae.datos).
 */
class PersonaSignosVitalesService
{
    /**
     * @return array{
     *   datos_sv: array<int, array<string, mixed>>,
     *   ultimos_sv: array<string, mixed>,
     *   total_sv: int,
     *   tiene_mas_sv: bool,
     *   es_actual: bool,
     *   fecha_titulo: string
     * }
     */
    public function getSignosVitalesData(Persona $persona, bool $simularSignos): array
    {
        $datos_sv = PersonaRepository::getDatosSignosVitales($persona);
        $ultimos_sv = PersonaRepository::getUltimosSignosVitales($datos_sv);

        if ($simularSignos) {
            $now = date('Y-m-d H:i:s');
            $fecha_formateada = date('d/m/Y H:i');

            $ultimos_sv = [
                'peso' => ['value' => '70.5', 'fecha' => $fecha_formateada],
                'talla' => ['value' => '172', 'fecha' => $fecha_formateada],
                'imc' => ['value' => '23.8', 'fecha' => $fecha_formateada],
                'ta' => [
                    'sistolica' => '120',
                    'diastolica' => '80',
                    'fecha' => $fecha_formateada,
                ],
            ];

            $datos_sv = [
                [
                    'fecha_atencion' => $now,
                    'peso' => 70.5,
                    'talla' => 172,
                    'imc' => 23.8,
                    'ta1_sistolica' => 120,
                    'ta1_diastolica' => 80,
                ],
                [
                    'fecha_atencion' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'peso' => 70.0,
                    'talla' => 172,
                    'imc' => 23.6,
                    'ta1_sistolica' => 118,
                    'ta1_diastolica' => 76,
                ],
                [
                    'fecha' => date('Y-m-d H:i:s', strtotime('-7 days')),
                    'ta' => '125/82',
                    'fc' => 75,
                    'fr' => 17,
                    'temperatura' => 36.8,
                    'peso' => 79.0,
                    'talla' => 172,
                ],
            ];
        }

        $total_sv = count($datos_sv);

        return [
            'datos_sv' => $datos_sv,
            'ultimos_sv' => $ultimos_sv,
            'total_sv' => $total_sv,
            'tiene_mas_sv' => $total_sv > 1,
            'es_actual' => $simularSignos,
            'fecha_titulo' => $this->resolveFechaTitulo($ultimos_sv),
        ];
    }

    /**
     * @param array<string, mixed> $ultimos_sv
     */
    private function resolveFechaTitulo(array $ultimos_sv): string
    {
        if (isset($ultimos_sv['peso']['fecha']) && $ultimos_sv['peso']['fecha'] !== '') {
            return (string) $ultimos_sv['peso']['fecha'];
        }
        if (isset($ultimos_sv['talla']['fecha']) && $ultimos_sv['talla']['fecha'] !== '') {
            return (string) $ultimos_sv['talla']['fecha'];
        }
        if (isset($ultimos_sv['imc']['fecha']) && $ultimos_sv['imc']['fecha'] !== '') {
            return (string) $ultimos_sv['imc']['fecha'];
        }
        if (isset($ultimos_sv['ta']['fecha']) && $ultimos_sv['ta']['fecha'] !== '') {
            return (string) $ultimos_sv['ta']['fecha'];
        }

        return '';
    }
}
