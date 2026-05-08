<?php

namespace frontend\modules\api\v1\controllers;

/**
 * Reemplazo del histórico `ConfigController` de API v1 (sin rutas activas en `urlManager`).
 *
 * **Fijar contexto operativo (efector + servicio + encounter + PES en sesión):**
 * - `POST /api/v1/sesion-operativa/establecer`
 * - Cuerpo: `{ "efector_id", "servicio_id", "encounter_class" }` (también acepta `id_efector` como alias del primero).
 * - Implementación: {@see \common\components\Services\SesionOperativa\SesionOperativaService::establecer} — resuelve o asegura fila
 *   {@see \common\models\ProfesionalEfectorServicio} vía {@see \common\components\Services\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService}
 *   cuando faltaba PES pero el servicio está habilitado en el efector (paridad con `SiteController::actionEstablecerSessionFinal`).
 *
 * **Opciones del wizard (sin fijar):** mismo `POST` con cuerpo incompleto → {@see \frontend\modules\api\v1\controllers\SesionOperativaController::actionEstablecer}.
 *
 * **Catálogos / listados:** `CatalogosController`, `RecursoHumanoController`, etc.
 */
class ConfigController extends BaseController
{
}
