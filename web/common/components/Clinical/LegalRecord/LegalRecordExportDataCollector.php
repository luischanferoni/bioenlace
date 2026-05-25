<?php

namespace common\components\Clinical\LegalRecord;

use common\components\Clinical\Enum\EncounterStatus;
use common\components\Clinical\PatientSummary\PatientEncounterSummaryBuilder;
use common\components\Person\Service\PersonaSignosVitalesService;
use common\models\Alergias;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterPatientSummary;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use common\models\Efector;
use common\models\Persona;
use common\models\PersonasAntecedente;

/**
 * Arma el payload estructurado del expediente legal (PDF).
 */
final class LegalRecordExportDataCollector
{
    /**
     * @return array<string, mixed>
     */
    public function collect(int $subjectPersonaId, ?int $idEfector = null): array
    {
        $persona = Persona::findOne(['id_persona' => $subjectPersonaId]);
        if ($persona === null) {
            throw new \InvalidArgumentException('Paciente no encontrado.');
        }

        $efector = $idEfector > 0 ? Efector::findOne((int) $idEfector) : null;

        return [
            'generatedAt' => date('Y-m-d H:i:s'),
            'efector' => [
                'id' => $idEfector > 0 ? (int) $idEfector : null,
                'nombre' => $efector ? (string) $efector->nombre : null,
            ],
            'paciente' => $this->buildPacienteBlock($persona, $idEfector),
            'informacionMedica' => $this->buildInformacionMedica($subjectPersonaId),
            'signosVitales' => (new PersonaSignosVitalesService())->getSignosVitalesData($persona, false),
            'atenciones' => $this->buildAtenciones($subjectPersonaId, $idEfector),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPacienteBlock(Persona $persona, ?int $idEfector): array
    {
        $hc = null;
        if ($idEfector > 0 && method_exists($persona, 'obtenerNHistoriaClinica')) {
            $hc = $persona->obtenerNHistoriaClinica((int) $idEfector);
        }

        return [
            'id' => (int) $persona->id_persona,
            'nombreCompleto' => method_exists($persona, 'getNombreCompleto')
                ? $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : trim($persona->nombre . ' ' . $persona->apellido),
            'documento' => $persona->documento,
            'edad' => $persona->edad ?? null,
            'fechaNacimiento' => $persona->fecha_nacimiento ?? null,
            'numeroHistoriaClinica' => $hc,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInformacionMedica(int $idPersona): array
    {
        [$condActivas, $condCronicas] = DCRepo::getCondicionesPaciente($idPersona);

        $mapCond = static function ($rows): array {
            $out = [];
            foreach ($rows as $c) {
                $out[] = [
                    'codigo' => isset($c->codigoSnomed) ? (string) $c->codigoSnomed->conceptId : null,
                    'termino' => isset($c->codigoSnomed) ? (string) $c->codigoSnomed->term : null,
                ];
            }

            return $out;
        };

        $alergias = [];
        foreach (Alergias::find()->where(['id_persona' => $idPersona])->all() as $a) {
            $alergias[] = [
                'codigo' => isset($a->codigoSnomed) ? (string) $a->codigoSnomed->conceptId : null,
                'termino' => isset($a->codigoSnomed) ? (string) $a->codigoSnomed->term : null,
            ];
        }

        $antecedentesPersonales = [];
        $antecedentesFamiliares = [];
        foreach (PersonasAntecedente::find()->where(['id_persona' => $idPersona])->all() as $ant) {
            $row = [
                'termino' => isset($ant->snomedSituacion) ? (string) $ant->snomedSituacion->term : null,
            ];
            if (($ant->tipo_antecedente ?? null) === 'Familiar') {
                $antecedentesFamiliares[] = $row;
            } else {
                $antecedentesPersonales[] = $row;
            }
        }

        return [
            'condicionesActivas' => $mapCond($condActivas),
            'condicionesCronicas' => $mapCond($condCronicas),
            'alergias' => $alergias,
            'antecedentesPersonales' => $antecedentesPersonales,
            'antecedentesFamiliares' => $antecedentesFamiliares,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAtenciones(int $subjectPersonaId, ?int $idEfector): array
    {
        $q = Encounter::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_AMB,
                'status' => EncounterStatus::FINISHED,
                'deleted_at' => null,
            ])
            ->orderBy(['period_end' => SORT_DESC, 'id' => SORT_DESC]);

        if ($idEfector > 0) {
            $q->andWhere(['efector_id' => (int) $idEfector]);
        }

        $builder = new PatientEncounterSummaryBuilder();
        $out = [];
        foreach ($q->all() as $encounter) {
            $dto = $builder->build($encounter);
            if ($dto === null) {
                continue;
            }
            $published = EncounterPatientSummary::findOne(['encounter_id' => (int) $encounter->id]);
            $out[] = array_merge($dto, [
                'resumenPublicadoPaciente' => $published !== null,
                'narrativePublicado' => $published ? trim((string) $published->narrative_text) : null,
            ]);
        }

        return $out;
    }
}
