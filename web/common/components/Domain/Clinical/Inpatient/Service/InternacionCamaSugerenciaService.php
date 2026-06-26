<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Guardia;
use common\models\InfraestructuraCama;
use common\models\Person\Persona;
use common\models\SegNivelInternacionHcama;

/**
 * Score de camas libres según requisitos clínicos declarados (agente F02).
 */
final class InternacionCamaSugerenciaService
{
    public const AGENT_ID = 'internacion-cama-sugerencia';

    /**
     * @param array<string, mixed> $requirements
     * @return list<array{id_cama: int, score: int, label: string, reasons: list<string>}>
     */
    public function rankCamas(int $idEfector, array $requirements): array
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null || $idEfector <= 0) {
            return [];
        }

        $scoring = is_array($config['scoring'] ?? null) ? $config['scoring'] : [];
        $topN = max(1, (int) ($config['top_n'] ?? 5));

        $rows = SegNivelInternacionHcama::getCamasDisponiblesForSelect($idEfector);
        $ranked = [];

        foreach ($rows as $row) {
            $idCama = (int) ($row['code'] ?? 0);
            if ($idCama <= 0) {
                continue;
            }

            $cama = InfraestructuraCama::find()
                ->with(['sala.piso', 'sala.servicio'])
                ->where(['id' => $idCama])
                ->one();
            if ($cama === null) {
                continue;
            }

            $result = $this->scoreCama($cama, $requirements, $scoring);
            if ($result['score'] <= 0) {
                continue;
            }

            $ranked[] = [
                'id_cama' => $idCama,
                'score' => $result['score'],
                'label' => (string) ($row['label'] ?? ('Cama ' . $idCama)),
                'reasons' => $result['reasons'],
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($ranked, 0, $topN);
    }

    /**
     * @param array<string, mixed> $requirements
     * @return array{respirador: bool, aislamiento: bool, pediatria: bool, id_servicio: int}
     */
    public function requirementsFromGuardia(?Guardia $guardia, ?Persona $persona): array
    {
        $text = $guardia !== null
            ? strtolower(trim((string) ($guardia->condiciones_derivacion ?? '')))
            : '';
        $edad = 0;
        if ($persona !== null && !empty($persona->fecha_nacimiento) && method_exists($persona, 'getEdad')) {
            $edad = (int) $persona->getEdad();
        }

        return [
            'respirador' => str_contains($text, 'oxigeno') || str_contains($text, 'o2'),
            'aislamiento' => str_contains($text, 'aislamiento') || str_contains($text, 'covid'),
            'pediatria' => $edad > 0 && $edad < 16,
            'id_servicio' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $requirements
     * @param array<string, mixed> $scoring
     * @return array{score: int, reasons: list<string>}
     */
    private function scoreCama(InfraestructuraCama $cama, array $requirements, array $scoring): array
    {
        $score = (int) ($scoring['estado_libre_bonus'] ?? 0);
        $reasons = ['disponible'];

        if (!empty($requirements['respirador'])) {
            if ((int) ($cama->respirador ?? 0) > 0) {
                $score += (int) ($scoring['respirador_required'] ?? 0);
                $reasons[] = 'respirador';
            } else {
                return ['score' => 0, 'reasons' => ['sin_respirador']];
            }
        }

        if ((int) ($cama->monitor ?? 0) > 0) {
            $score += (int) ($scoring['monitor_bonus'] ?? 0);
            $reasons[] = 'monitor';
        }

        $sala = $cama->sala;
        if ($sala !== null) {
            $idServicioReq = (int) ($requirements['id_servicio'] ?? 0);
            if ($idServicioReq > 0 && (int) ($sala->id_servicio ?? 0) === $idServicioReq) {
                $score += (int) ($scoring['sala_servicio_match'] ?? 0);
                $reasons[] = 'servicio';
            }

            if (!empty($requirements['aislamiento']) && (int) ($sala->covid ?? 0) > 0) {
                $score += (int) ($scoring['covid_aislamiento_match'] ?? 0);
                $reasons[] = 'aislamiento';
            }

            $tipoSala = strtolower(trim((string) ($sala->tipo_sala ?? '')));
            $desc = strtolower(trim((string) ($sala->descripcion ?? '')));
            if (!empty($requirements['pediatria'])
                && (str_contains($tipoSala, 'pedi') || str_contains($desc, 'pedi'))) {
                $score += (int) ($scoring['pediatria_match'] ?? 0);
                $reasons[] = 'pediatria';
            } elseif (!empty($requirements['pediatria'])) {
                return ['score' => 0, 'reasons' => ['no_pediatrica']];
            }
        }

        return ['score' => $score, 'reasons' => $reasons];
    }
}
