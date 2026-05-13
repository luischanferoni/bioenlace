<?php

namespace common\components\Services\Turnos;

use Yii;
use common\models\Turno;
use common\models\EfectorTurnosConfig;

/**
 * Horas mínimas antes del inicio del turno para autogestión (cancelar / reprogramar) por app.
 *
 * Config por efector en {@see EfectorTurnosConfig}:
 * - `null` → se usa {@see Yii::$app->params} `efectorTurnosConfigDefaults`
 * - `0` → sin restricción por anticipación
 * - entero &gt; 0 → horas mínimas requeridas
 */
class TurnoAutogestionAnticipacionService
{
    /**
     * @return int>=0
     */
    public function minHorasAntesCancelarParaEfector(int $idEfector): int
    {
        if ($idEfector <= 0) {
            return $this->defaultMinHoras('autogestion_min_horas_antes_cancelar');
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector($idEfector);
        $v = $cfg->autogestion_min_horas_antes_cancelar;
        if ($v === null) {
            return $this->defaultMinHoras('autogestion_min_horas_antes_cancelar');
        }

        return max(0, (int) $v);
    }

    /**
     * @return int>=0
     */
    public function minHorasAntesReprogramarParaEfector(int $idEfector): int
    {
        if ($idEfector <= 0) {
            return $this->defaultMinHoras('autogestion_min_horas_antes_reprogramar');
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector($idEfector);
        $v = $cfg->autogestion_min_horas_antes_reprogramar;
        if ($v === null) {
            return $this->defaultMinHoras('autogestion_min_horas_antes_reprogramar');
        }

        return max(0, (int) $v);
    }

    /**
     * @throws AutogestionAnticipacionException
     */
    public function assertPuedeCancelarPorApp(Turno $turno): void
    {
        $idEf = (int) ($turno->id_efector ?? 0);
        $h = $this->minHorasAntesCancelarParaEfector($idEf);
        if ($h <= 0) {
            return;
        }
        if (!$this->ahoraEsAntesDeLimite($turno, $h)) {
            throw new AutogestionAnticipacionException(
                'Ya no podés cancelar este turno por la app: el efector exige hacerlo con al menos '
                . $h . ' hora' . ($h === 1 ? '' : 's') . ' de anticipación. Contactá al consultorio.'
            );
        }
    }

    /**
     * @throws AutogestionAnticipacionException
     */
    public function assertPuedeReprogramarPorApp(Turno $turno): void
    {
        $idEf = (int) ($turno->id_efector ?? 0);
        $h = $this->minHorasAntesReprogramarParaEfector($idEf);
        if ($h <= 0) {
            return;
        }
        if (!$this->ahoraEsAntesDeLimite($turno, $h)) {
            throw new AutogestionAnticipacionException(
                'Ya no podés reprogramar este turno por la app: el efector exige hacerlo con al menos '
                . $h . ' hora' . ($h === 1 ? '' : 's') . ' de anticipación. Contactá al consultorio.'
            );
        }
    }

    /**
     * @return bool true si aún está antes del (inicio del turno − $horas)
     */
    public function ahoraEsAntesDeLimite(Turno $turno, int $horas): bool
    {
        if ($horas <= 0) {
            return true;
        }
        $start = $this->inicioTurnoUnix($turno);
        if ($start === null) {
            return true;
        }
        $limite = $start - $horas * 3600;

        return time() < $limite;
    }

    /**
     * @return int|null unix timestamp
     */
    public function inicioTurnoUnix(Turno $turno)
    {
        $fecha = trim((string) $turno->fecha);
        $hora = trim((string) $turno->hora);
        if ($fecha === '' || $hora === '') {
            return null;
        }
        $ts = strtotime($fecha . ' ' . $hora);
        if ($ts === false) {
            return null;
        }

        return $ts;
    }

    /**
     * @param string $key autogestion_min_horas_antes_cancelar|autogestion_min_horas_antes_reprogramar
     * @return int>=0
     */
    protected function defaultMinHoras($key): int
    {
        $defaults = Yii::$app->params['efectorTurnosConfigDefaults'] ?? [];

        return max(0, (int) ($defaults[$key] ?? 0));
    }

    /**
     * Texto informativo para UI de reserva (horarios) según anticipación mínima del efector.
     */
    public function textoLeyendaPoliticaAutogestionApp(int $idEfector): string
    {
        $hC = $this->minHorasAntesCancelarParaEfector($idEfector);
        $hR = $this->minHorasAntesReprogramarParaEfector($idEfector);

        $lineas = [
            'Estas reglas aplican al inicio del turno que elijas:',
        ];
        if ($hC <= 0) {
            $lineas[] = '• Cancelar por app: sin mínimo de horas de anticipación.';
        } else {
            $lineas[] = '• Cancelar por app: al menos ' . $hC . ' hora' . ($hC === 1 ? '' : 's') . ' antes del horario reservado.';
        }
        if ($hR <= 0) {
            $lineas[] = '• Cambiar el horario (reprogramar) por app: sin mínimo de horas de anticipación.';
        } else {
            $lineas[] = '• Cambiar el horario (reprogramar) por app: al menos ' . $hR . ' hora' . ($hR === 1 ? '' : 's') . ' antes del horario reservado.';
        }
        $lineas[] = 'Pasado ese plazo o en situaciones especiales, contactá al consultorio.';

        return implode("\n", $lineas);
    }
}
