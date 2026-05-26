<?php

namespace common\components\Emergency;

use common\components\Clinical\PatientHistoriaUrl;
use common\models\Clinical\Encounter;
use common\models\Guardia;
use Yii;

/**
 * Asignación, inicio de atención, derivación y egreso de episodios de guardia.
 */
final class GuardiaOperacionService
{
    /** @var GuardiaCircuitoService */
    private $circuito;

    /** @var GuardiaEncounterResolver */
    private $encounterResolver;

    public function __construct(?GuardiaCircuitoService $circuito = null, ?GuardiaEncounterResolver $encounterResolver = null)
    {
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
        $this->encounterResolver = $encounterResolver ?? new GuardiaEncounterResolver();
    }

    /**
     * @return array<string, mixed>
     */
    public function asignar(int $guardiaId, int $idPes, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        if ($idPes <= 0) {
            throw new \InvalidArgumentException('Se requiere id_profesional_efector_servicio.');
        }
        $guardia->id_profesional_efector_servicio = $idPes;
        $guardia->updateAttributes(['id_profesional_efector_servicio' => $idPes]);
        $this->circuito->recordEvent($guardiaId, CircuitoEventType::ASIGNACION, $idPes, [
            'id_profesional_efector_servicio' => $idPes,
        ]);
        $guardia = Guardia::find()->where(['id' => $guardiaId])->with('paciente')->one() ?? $guardia;
        (new GuardiaPushNotifier())->notifyAssigned($guardia, $idPes);

        return $this->serializeOperacion($guardia);
    }

    /**
     * Marca en atención y devuelve URL de captura clínica (consulta se crea al abrir historia).
     *
     * @return array<string, mixed>
     */
    public function iniciarAtencion(int $guardiaId, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        $estado = $this->circuito->effectiveEstado($guardia);
        if ($estado === CircuitoEstado::ESPERA_TRIAGE) {
            throw new \InvalidArgumentException('Registre el triage antes de iniciar la atención.');
        }
        if ($estado === CircuitoEstado::FINALIZADO || $estado === CircuitoEstado::DERIVADO) {
            throw new \InvalidArgumentException('No se puede atender un episodio cerrado o derivado.');
        }

        $guardia->circuito_estado = CircuitoEstado::EN_ATENCION;
        $guardia->estado = Guardia::ESTADO_ATENDIDA;
        $guardia->updateAttributes([
            'circuito_estado' => $guardia->circuito_estado,
            'estado' => $guardia->estado,
        ]);

        $pesId = GuardiaEfectorAccess::resolvePesId(null);
        $this->circuito->recordEvent($guardiaId, CircuitoEventType::INICIO_ATENCION, $pesId);

        $encounter = $this->encounterResolver->findLatestForGuardia((int) $guardia->id);
        $capturaUrl = PatientHistoriaUrl::captura(
            (int) $guardia->id_persona,
            Encounter::PARENT_GUARDIA,
            (int) $guardia->id
        );

        $out = $this->serializeOperacion($guardia);
        $out['captura_url'] = $capturaUrl;
        $out['encounter_id'] = $encounter !== null ? (int) $encounter->id : null;

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function derivar(int $guardiaId, array $body, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        $idEfectorDerivacion = (int) ($body['id_efector_derivacion'] ?? 0);
        if ($idEfectorDerivacion <= 0) {
            throw new \InvalidArgumentException('Se requiere id_efector_derivacion.');
        }
        $guardia->id_efector_derivacion = $idEfectorDerivacion;
        $guardia->condiciones_derivacion = isset($body['condiciones_derivacion'])
            ? (string) $body['condiciones_derivacion']
            : null;
        if (!empty($body['solicitar_internacion'])) {
            $idInternacionEfector = (int) ($body['notificar_internacion_id_efector'] ?? $idEfectorDerivacion);
            if ($idInternacionEfector > 0) {
                $guardia->notificar_internacion_id_efector = $idInternacionEfector;
            }
        } elseif (isset($body['notificar_internacion_id_efector'])) {
            $guardia->notificar_internacion_id_efector = (int) $body['notificar_internacion_id_efector'] ?: null;
        }
        $guardia->circuito_estado = CircuitoEstado::DERIVADO;
        $guardia->updateAttributes([
            'id_efector_derivacion' => $guardia->id_efector_derivacion,
            'condiciones_derivacion' => $guardia->condiciones_derivacion,
            'notificar_internacion_id_efector' => $guardia->notificar_internacion_id_efector,
            'circuito_estado' => $guardia->circuito_estado,
        ]);
        $this->circuito->recordEvent($guardiaId, CircuitoEventType::DERIVACION, null, [
            'id_efector_derivacion' => $idEfectorDerivacion,
        ]);

        return $this->serializeOperacion($guardia);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function finalizar(int $guardiaId, array $body, int $idEfector): array
    {
        $guardia = $this->loadGuardia($guardiaId, $idEfector);
        $fechaFin = isset($body['fecha_fin']) ? (string) $body['fecha_fin'] : date('d/m/Y');
        $horaFin = isset($body['hora_fin']) ? (string) $body['hora_fin'] : date('H:i');

        $guardia->scenario = Guardia::EGRESO_PACIENTE;
        $guardia->fecha_fin = $fechaFin;
        $guardia->hora_fin = $horaFin;
        if (!$guardia->validate()) {
            throw new \InvalidArgumentException(
                'Egreso inválido: ' . json_encode($guardia->errors, JSON_UNESCAPED_UNICODE)
            );
        }
        if (!$guardia->save(false)) {
            throw new \RuntimeException('No se pudo finalizar la guardia.');
        }

        $guardia->circuito_estado = CircuitoEstado::FINALIZADO;
        $guardia->updateAttributes(['circuito_estado' => CircuitoEstado::FINALIZADO]);
        $this->circuito->recordEvent($guardiaId, CircuitoEventType::EGRESO, null);

        return $this->serializeOperacion($guardia);
    }

    private function loadGuardia(int $guardiaId, int $idEfector): Guardia
    {
        $guardia = Guardia::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        GuardiaEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);

        return $guardia;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOperacion(Guardia $guardia): array
    {
        return [
            'id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'estado' => $guardia->estado,
            'circuito_estado' => $this->circuito->effectiveEstado($guardia),
            'circuito_estado_label' => CircuitoEstado::label($this->circuito->effectiveEstado($guardia)),
            'id_profesional_efector_servicio' => $guardia->id_profesional_efector_servicio,
        ];
    }
}
