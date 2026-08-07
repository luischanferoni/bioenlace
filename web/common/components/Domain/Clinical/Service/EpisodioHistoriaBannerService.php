<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Domain\Clinical\Emergency\Service\GuardiaCircuitoService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaTriageService;
use common\models\Clinical\Encounter;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use common\models\Person\Persona;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionHcama;

/**
 * Banner de episodio para HC / captura EMER e IMP (triaje, ubicación, equipo, motivo).
 * No sustituye el estado longitudinal del paciente (alergias, crónicos).
 */
final class EpisodioHistoriaBannerService
{
    /** @var GuardiaCircuitoService */
    private $circuito;

    /** @var GuardiaTriageService */
    private $triageSerializer;

    public function __construct(
        ?GuardiaCircuitoService $circuito = null,
        ?GuardiaTriageService $triageSerializer = null
    ) {
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
        $this->triageSerializer = $triageSerializer ?? new GuardiaTriageService();
    }

    /**
     * @return array{
     *   contexto_episodio: array<string, mixed>,
     *   contexto_internacion: null
     * }|null
     */
    public function buildForGuardia(int $personaId, int $guardiaId, int $idEfector): ?array
    {
        $guardia = Guardia::find()
            ->where(['id' => $guardiaId, 'id_efector' => $idEfector])
            ->with(['profesionalEfectorServicio.persona'])
            ->one();
        if ($guardia === null || (int) $guardia->id_persona !== $personaId) {
            return null;
        }

        $estado = $this->circuito->effectiveEstado($guardia);
        $triageRow = GuardiaTriage::findOne(['guardia_id' => $guardiaId]);
        $triage = $triageRow !== null ? $this->triageSerializer->serializeTriage($triageRow) : null;

        $motivo = null;
        if (is_array($triage)) {
            $rt = trim((string) ($triage['reason_text'] ?? ''));
            if ($rt !== '') {
                $motivo = $rt;
            }
        }
        if ($motivo === null) {
            $sit = trim((string) ($guardia->situacion_al_ingresar ?? ''));
            $motivo = $sit !== '' ? $sit : null;
        }

        $ingresoAt = trim((string) ($guardia->ingreso_at ?? ''));
        if ($ingresoAt === '') {
            $fecha = trim((string) ($guardia->fecha ?? ''));
            $hora = trim((string) ($guardia->hora ?? ''));
            $ingresoAt = trim($fecha . ($hora !== '' ? ' ' . substr($hora, 0, 5) : ''));
        }

        $banner = $this->baseBanner(
            Encounter::PARENT_GUARDIA,
            $guardiaId,
            $estado,
            CircuitoEstado::label($estado),
            $motivo,
            $ingresoAt !== '' ? $ingresoAt : null
        );
        $banner['triage'] = $triage;
        $banner['ubicacion'] = null;
        $pesPersona = null;
        if ($guardia->profesionalEfectorServicio !== null) {
            $pesPersona = $guardia->profesionalEfectorServicio->persona;
        }
        $banner['equipo'] = [
            'medico' => $this->serializeMedicoPes(
                $guardia->id_profesional_efector_servicio !== null
                    ? (int) $guardia->id_profesional_efector_servicio
                    : null,
                $pesPersona
            ),
        ];

        // Retiro / abandono: solo tablero (⋮ móvil o CTA web), no en la HC.
        $banner['acciones'] = [];

        return [
            'contexto_episodio' => $banner,
            'contexto_internacion' => null,
        ];
    }

    /**
     * @return array{
     *   contexto_episodio: array<string, mixed>,
     *   contexto_internacion: array<string, mixed>
     * }|null
     */
    public function buildForInternacion(int $personaId, int $internacionId): ?array
    {
        $internacion = SegNivelInternacion::find()
            ->where(['id' => $internacionId])
            ->with(['profesionalEfectorServicio.persona'])
            ->one();
        if (
            !$internacion instanceof SegNivelInternacion
            || (int) $internacion->id_persona !== $personaId
        ) {
            return null;
        }

        $camaLabel = '';
        $idCama = (int) ($internacion->id_cama ?? 0);
        if ($idCama > 0) {
            $cama = SegNivelInternacionHcama::getCamaActualLabel($idCama);
            $camaLabel = trim((string) ($cama['label'] ?? ''));
        }

        $fechaInicio = '';
        if (!empty($internacion->fecha_inicio)) {
            $fechaInicio = (string) $internacion->fecha_inicio;
            if (!empty($internacion->hora_inicio)) {
                $fechaInicio .= ' ' . substr((string) $internacion->hora_inicio, 0, 5);
            }
        }

        $motivo = trim((string) ($internacion->situacion_al_ingresar ?? ''));
        $motivo = $motivo !== '' ? $motivo : null;

        $pesPersona = null;
        if ($internacion->profesionalEfectorServicio !== null) {
            $pesPersona = $internacion->profesionalEfectorServicio->persona;
        }
        $medico = $this->serializeMedicoPes(
            $internacion->id_profesional_efector_servicio !== null
                ? (int) $internacion->id_profesional_efector_servicio
                : null,
            $pesPersona
        );

        $evoluciones = $this->listEvolucionesInternacion($personaId, $internacionId);

        $banner = $this->baseBanner(
            Encounter::PARENT_INTERNACION,
            $internacionId,
            null,
            null,
            $motivo,
            $fechaInicio !== '' ? $fechaInicio : null
        );
        $banner['triage'] = null;
        $banner['ubicacion'] = $camaLabel !== '' ? ['label' => $camaLabel] : null;
        $banner['equipo'] = ['medico' => $medico];
        $banner['acciones'] = [];

        $contextoInternacion = [
            'internacion_id' => $internacionId,
            'cama_label' => $camaLabel,
            'fecha_inicio' => $fechaInicio,
            'evoluciones' => $evoluciones,
            'medico' => $medico,
            'motivo' => $motivo,
        ];

        return [
            'contexto_episodio' => $banner,
            'contexto_internacion' => $contextoInternacion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseBanner(
        string $tipo,
        int $episodioId,
        ?string $estado,
        ?string $estadoLabel,
        ?string $motivo,
        ?string $ingresoAt
    ): array {
        return [
            'tipo' => $tipo,
            'episodio_id' => $episodioId,
            'estado' => $estado,
            'estado_label' => $estadoLabel,
            'motivo' => $motivo,
            'ingreso_at' => $ingresoAt,
        ];
    }

    /**
     * @return array{pes_id: int, nombre: string}|null
     */
    private function serializeMedicoPes(?int $pesId, $persona): ?array
    {
        if ($pesId === null || $pesId <= 0) {
            return null;
        }
        $nombre = '';
        if ($persona instanceof Persona) {
            $nombre = trim((string) $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON));
        }
        if ($nombre === '') {
            $nombre = 'PES #' . $pesId;
        }

        return [
            'pes_id' => $pesId,
            'nombre' => $nombre,
        ];
    }

    /**
     * @return list<array{encounter_id: int, fecha: string, texto: string, status: string}>
     */
    private function listEvolucionesInternacion(int $personaId, int $internacionId): array
    {
        $parentType = Encounter::PARENT_CLASSES[Encounter::PARENT_INTERNACION];
        $rows = Encounter::find()
            ->andWhere([
                'parent_type' => $parentType,
                'parent_id' => $internacionId,
                'subject_persona_id' => $personaId,
            ])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['period_start' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(25)
            ->all();

        $evoluciones = [];
        foreach ($rows as $enc) {
            if (!$enc instanceof Encounter) {
                continue;
            }
            $texto = trim((string) ($enc->note ?? ''));
            if ($texto === '') {
                $texto = trim((string) ($enc->reason_text ?? ''));
            }
            if ($texto === '' || stripos($texto, 'Internación #') === 0) {
                continue;
            }
            $fecha = (string) ($enc->period_start ?? $enc->created_at ?? '');
            $evoluciones[] = [
                'encounter_id' => (int) $enc->id,
                'fecha' => $fecha,
                'texto' => $texto,
                'status' => (string) ($enc->status ?? ''),
            ];
        }

        return $evoluciones;
    }
}
