<?php

namespace frontend\modules\api\v1\controllers;

/**
 * Agenda profesional (RRHH): listados por día / recurso.
 *
 * GET /api/v1/agenda/dia — mis turnos como médico/profesional (query: fecha, rrhh_id opcional).
 */
class AgendaController extends BaseController
{
    /**
     * Turnos del día para la agenda del profesional. RBAC: ruta /api/agenda/dia
     */
    public function actionDia()
    {
        return TurnosController::agendaDiaResponse();
    }
}
