<?php

namespace common\components\Services\Turnos;

use Yii;
use common\models\Turno;

/**
 * Contexto del actor al persistir un turno (autogestión vs gestión para tercero).
 */
final class TurnoCreacionContext
{
    /** @var int|null id_persona del usuario autenticado */
    public $idPersonaAutenticada;

    /** @var int|null efector operativo en sesión (staff), opcional para paciente móvil */
    public $idEfectorSesion;

    /** @var int|null PES del profesional en sesión */
    public $idProfesionalEfectorServicioSesion;

    /**
     * @param int|string|null $idPersonaAutenticada
     * @param int|string|null $idEfectorSesion
     * @param int|string|null $idProfesionalEfectorServicioSesion
     */
    public function __construct($idPersonaAutenticada, $idEfectorSesion, $idProfesionalEfectorServicioSesion)
    {
        $this->idPersonaAutenticada = $idPersonaAutenticada !== null && $idPersonaAutenticada !== ''
            ? (int) $idPersonaAutenticada : null;
        $this->idEfectorSesion = $idEfectorSesion !== null && $idEfectorSesion !== ''
            ? (int) $idEfectorSesion : null;
        $this->idProfesionalEfectorServicioSesion = $idProfesionalEfectorServicioSesion !== null && $idProfesionalEfectorServicioSesion !== ''
            ? (int) $idProfesionalEfectorServicioSesion : null;
    }

    /**
     * Paciente reservando para sí mismo (políticas de autogestión y defaults de RRHH de sesión aplican).
     */
    public function esReservaParaSiMismo(Turno $model): bool
    {
        return $this->idPersonaAutenticada !== null
            && (int) $model->id_persona === (int) $this->idPersonaAutenticada;
    }

    /** @return self */
    public static function fromCurrentUser()
    {
        $u = Yii::$app->user;

        $idP = $u->getIdPersona();
        $idE = $u->getIdEfector();
        $idR = $u->getIdProfesionalEfectorServicio();

        return new self(
            $idP ?: null,
            $idE ?: null,
            $idR ?: null
        );
    }
}
