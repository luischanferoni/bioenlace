<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Generación de resúmenes con IA del historial del paciente (texto base y resúmenes por servicio).
 *
 * Pensado para ejecutarse desde cron. Por ahora es un stub; la lógica se implementará según
 * el plan en web/docs/RESUMEN_TIMELINE_PACIENTE_IA.md (texto base, sensibilidad, resumen por servicio).
 *
 * Uso:
 *   php yii resumen-paciente/generar              # Procesar todos los pacientes/servicios que apliquen
 *   php yii resumen-paciente/generar --personaId=123
 *   php yii resumen-paciente/generar --personaId=123 --servicioId=5
 */
class ResumenPacienteController extends Controller
{
    /**
     * @var int|null ID de persona a procesar (opcional). Si no se indica, se procesan todos los que apliquen.
     */
    public $personaId;

    /**
     * @var int|null ID de servicio (opcional). Si se indica junto con personaId, solo se genera el resumen para ese servicio.
     */
    public $servicioId;

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['personaId', 'servicioId']);
    }

    /**
     * {@inheritdoc}
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'personaId',
            's' => 'servicioId',
        ]);
    }

    /**
     * Generar/actualizar texto base y resúmenes por servicio para uno o todos los pacientes.
     * Stub: solo escribe en consola; la lógica real se añadirá según el plan de resumen con IA.
     */
    public function actionGenerar()
    {
        $this->stdout("Resumen paciente (stub) - preparado para cron.\n", Console::FG_YELLOW);
        $this->stdout("Ver plan: web/docs/RESUMEN_TIMELINE_PACIENTE_IA.md\n");
        if ($this->personaId !== null) {
            $this->stdout("Persona ID: {$this->personaId}\n");
        }
        if ($this->servicioId !== null) {
            $this->stdout("Servicio ID: {$this->servicioId}\n");
        }
        $this->stdout("La lógica de texto base y resúmenes por servicio se implementará aquí.\n");
        return self::EXIT_CODE_NORMAL;
    }
}
