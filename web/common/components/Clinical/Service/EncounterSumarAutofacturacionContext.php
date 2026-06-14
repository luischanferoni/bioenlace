<?php

namespace common\components\Clinical\Service;

use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\Guardia;
use common\models\Person\Persona;
use common\models\SegNivelInternacion;
use common\models\Scheduling\Turno;
use common\models\sumar\Autofacturacion;
use Yii;

/**
 * Contexto SUMAR para autofacturación sobre un {@see Encounter} (sin tabla `consultas`).
 */
final class EncounterSumarAutofacturacionContext
{
    public function __construct(public Encounter $encounter)
    {
    }

    public static function resolveEncounterIdFromRequest(array $input): ?int
    {
        foreach (['encounter_id', 'legacy_id_consulta', 'id_consulta'] as $key) {
            if (!empty($input[$key])) {
                return (int) $input[$key];
            }
        }

        return null;
    }

    public static function fromRequest(array $input): ?self
    {
        $id = self::resolveEncounterIdFromRequest($input);
        if ($id === null || $id <= 0) {
            return null;
        }
        $encounter = Encounter::findOne($id);

        return $encounter !== null ? new self($encounter) : null;
    }

    public function obtenerPaciente(): ?Persona
    {
        $persona = $this->encounter->subject;
        if ($persona !== null) {
            return $persona;
        }

        $turno = $this->encounter->appointment;
        if ($turno !== null && $turno->paciente !== null) {
            return $turno->paciente;
        }

        return null;
    }

    /**
     * Código de ámbito SUMAR según parent del encounter.
     *
     * @return int|string
     */
    public function ambitoSumar()
    {
        $parentType = (string) ($this->encounter->parent_type ?? '');
        if ($parentType === SegNivelInternacion::class || $parentType === Encounter::PARENT_CLASSES[Encounter::PARENT_INTERNACION]) {
            return 'INTERNACION';
        }
        if ($parentType === Guardia::class || $parentType === Encounter::PARENT_CLASSES[Encounter::PARENT_GUARDIA]) {
            return 'GUARDIA';
        }
        if ($parentType === Turno::class || $parentType === Encounter::PARENT_CLASSES[Encounter::PARENT_TURNO]
            || $this->encounter->appointment_id) {
            return 712877007;
        }

        return 712877007;
    }

    /**
     * @return list<string>
     */
    public function codigosDiagnosticosSnomed(): array
    {
        $codes = [];
        foreach ($this->encounter->conditions as $condition) {
            /** @var Condition $condition */
            $code = trim((string) ($condition->code ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public function fechaReferenciaPaciente(): ?string
    {
        $turno = $this->encounter->appointment;
        if ($turno !== null && !empty($turno->fecha)) {
            return (string) $turno->fecha;
        }

        $created = (string) ($this->encounter->created_at ?? '');
        if ($created !== '') {
            return substr($created, 0, 10);
        }

        return null;
    }

    public function fechaPrestacion(): string
    {
        $created = (string) ($this->encounter->created_at ?? '');
        if ($created !== '') {
            $parts = explode(' ', $created);

            return $parts[0] ?: date('Y-m-d');
        }

        return date('Y-m-d');
    }

    public function getAutofacturacion(): ?Autofacturacion
    {
        $fk = Autofacturacion::legacyConsultaFkAttribute();

        return Autofacturacion::findOne([$fk => (int) $this->encounter->id]);
    }

    /**
     * @return Condition[]
     */
    public function condicionesParaVista(): array
    {
        return $this->encounter->conditions;
    }

    /**
     * @return ServiceRequest[]
     */
    public function practicasParaVista(): array
    {
        return $this->encounter->getServiceRequests()->all();
    }
}
