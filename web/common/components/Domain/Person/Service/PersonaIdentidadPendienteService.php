<?php

namespace common\components\Domain\Person\Service;

use common\models\Clinical\CarePlan;
use common\models\Clinical\Condition;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterPatientSummary;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\Procedure;
use common\models\Clinical\ServiceRequest;
use common\models\Clinical\VisionPrescription;
use common\models\Guardia;
use common\models\Person\Persona;
use common\models\SegNivelInternacion;

/**
 * Placeholder de Persona por episodio NN (sin documento). No es padrón ni MPI.
 */
final class PersonaIdentidadPendienteService
{
    public function crearPlaceholder(): Persona
    {
        $persona = new Persona();
        $persona->scenario = Persona::SCENARIO_IDENTIDAD_PENDIENTE;
        $persona->apellido = 'NN';
        $persona->nombre = 'Sin identificar';
        $persona->documento = null;
        $persona->acredita_identidad = 0;
        $persona->sexo_biologico = 3;
        $persona->genero = 3;
        $persona->sexo = 'I';
        $persona->id_tipodoc = 1;
        $persona->id_estado_civil = 1;
        $persona->documento_propio = 1;

        if (!$persona->save()) {
            throw new \RuntimeException(
                'No se pudo crear la identidad pendiente: ' . json_encode($persona->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $persona;
    }

    public static function esPlaceholder(Persona $persona): bool
    {
        return (int) $persona->acredita_identidad === 0
            && trim((string) ($persona->documento ?? '')) === '';
    }

    /**
     * Mueve el sujeto clínico del episodio de guardia a la Persona definitiva.
     * No fusiona padrones MPI: solo retarget de filas de este episodio.
     */
    public function retargetEpisodioGuardia(int $guardiaId, int $fromPersonaId, int $toPersonaId): void
    {
        if ($fromPersonaId <= 0 || $toPersonaId <= 0 || $fromPersonaId === $toPersonaId) {
            return;
        }

        $parentTypes = [Encounter::PARENT_GUARDIA, Guardia::class];
        $encounterIds = Encounter::find()
            ->select('id')
            ->where(['parent_id' => $guardiaId, 'subject_persona_id' => $fromPersonaId])
            ->andWhere(['parent_type' => $parentTypes])
            ->column();

        if ($encounterIds !== []) {
            Encounter::updateAll(
                ['subject_persona_id' => $toPersonaId],
                ['id' => $encounterIds]
            );
            foreach ($this->clinicalClassesWithEncounter() as $class) {
                $class::updateAll(
                    ['subject_persona_id' => $toPersonaId],
                    ['encounter_id' => $encounterIds, 'subject_persona_id' => $fromPersonaId]
                );
            }
        }

        SegNivelInternacion::updateAll(
            ['id_persona' => $toPersonaId],
            ['id_guardia' => $guardiaId, 'id_persona' => $fromPersonaId]
        );

        $placeholder = Persona::findOne($fromPersonaId);
        if ($placeholder !== null && self::esPlaceholder($placeholder)) {
            $placeholder->deleted_at = date('Y-m-d H:i:s');
            $placeholder->save(false);
        }
    }

    /**
     * @return list<class-string>
     */
    private function clinicalClassesWithEncounter(): array
    {
        return [
            Condition::class,
            MedicationRequest::class,
            ServiceRequest::class,
            DiagnosticReport::class,
            Procedure::class,
            CarePlan::class,
            EncounterCapture::class,
            EncounterPatientSummary::class,
            ElectronicPrescription::class,
            VisionPrescription::class,
        ];
    }
}
