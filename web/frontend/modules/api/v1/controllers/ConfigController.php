<?php

namespace frontend\modules\api\v1\controllers;

/**
 * (DEPRECADO) Controller histórico de Config en API v1.
 *
 * Reemplazado por:
 * - `SesionOperativaController::actionEstablecer`   (POST `/api/v1/sesion-operativa/establecer`, modo opciones sin body o modo fijar con selección)
 * - `RrhhController::actionMisServiciosEnEfector`   (GET|POST `/api/v1/rrhh/mis-servicios-en-efector`; `id_efector` opcional si hay sesión RRHH)
 * - `CatalogosController::actionEncounterClasses`   (GET `/api/v1/catalogos/encounter-classes`)
 */
class ConfigController extends BaseController
{
}

