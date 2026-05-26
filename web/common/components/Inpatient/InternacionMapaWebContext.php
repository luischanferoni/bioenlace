<?php

namespace common\components\Inpatient;

use common\models\InfraestructuraPiso;
use common\models\Persona;
use common\models\SegNivelInternacion;
use Yii;

/**
 * Contexto web compartido para el mapa de camas (inicio IMP y panel operativo).
 */
final class InternacionMapaWebContext
{
    /**
     * @return array{mapa: array<string, mixed>|null, pisos_efector: array, paciente_internado: bool}
     */
    public static function build(int $idEfector, ?int $idPiso = null, ?int $idSala = null): array
    {
        $pisos = new InfraestructuraPiso();
        $pisosEfector = $idEfector > 0 ? $pisos->pisosPorEfector($idEfector) : [];

        $mapa = null;
        if ($idEfector > 0) {
            try {
                $mapa = (new InternacionMapaCamasService())->mapa($idEfector, $idPiso, $idSala);
            } catch (\Throwable $e) {
                Yii::warning('Mapa de camas: ' . $e->getMessage(), __METHOD__);
            }
        }

        return [
            'mapa' => $mapa,
            'pisos_efector' => $pisosEfector,
            'paciente_internado' => self::pacienteEnSesionYaInternado(),
        ];
    }

    public static function pacienteEnSesionYaInternado(): bool
    {
        $raw = Yii::$app->session['persona'] ?? null;
        if ($raw === null) {
            return false;
        }
        $persona = @unserialize($raw);
        if (!$persona instanceof Persona || empty($persona->id_persona)) {
            return false;
        }

        return SegNivelInternacion::personaInternada((int) $persona->id_persona);
    }
}
