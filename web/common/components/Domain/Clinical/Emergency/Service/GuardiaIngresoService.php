<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\models\Guardia;
use Yii;

final class GuardiaIngresoService
{
    /** @var GuardiaCircuitoService */
    private $circuito;

    public function __construct(?GuardiaCircuitoService $circuito = null)
    {
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function ingresar(array $body, int $idEfector): array
    {
        $idPersona = (int) ($body['id_persona'] ?? 0);
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('Se requiere id_persona.');
        }

        $ingresaEn = (string) ($body['ingresa_en'] ?? 'deambula');
        $ingresaCon = (string) ($body['ingresa_con'] ?? 'solo');
        if (!isset(Guardia::INGRESO_EN[$ingresaEn])) {
            throw new \InvalidArgumentException('ingresa_en inválido.');
        }
        if (!isset(Guardia::INGRESO_CON[$ingresaCon])) {
            throw new \InvalidArgumentException('ingresa_con inválido.');
        }

        $model = new Guardia();
        $model->scenario = Guardia::INGRESO_PACIENTE;
        $model->id_persona = $idPersona;
        $model->id_efector = $idEfector;
        $model->ingresa_en = $ingresaEn;
        $model->ingresa_con = $ingresaCon;
        $model->cobertura = isset($body['cobertura']) ? (string) $body['cobertura'] : null;
        $model->situacion_al_ingresar = isset($body['situacion_al_ingresar'])
            ? (string) $body['situacion_al_ingresar']
            : null;
        $model->datos_contacto_tel = (string) ($body['datos_contacto_tel'] ?? '');
        $model->fecha = date('d/m/Y');
        $model->hora = date('H:i');

        $pes = GuardiaEfectorAccess::resolvePesId(
            isset($body['id_profesional_efector_servicio'])
                ? (int) $body['id_profesional_efector_servicio']
                : null
        );
        if ($pes !== null) {
            $model->id_profesional_efector_servicio = $pes;
        }

        if (!$model->validate()) {
            throw new \InvalidArgumentException(
                'Datos de ingreso inválidos: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE)
            );
        }
        if (!$model->save(false)) {
            throw new \RuntimeException('No se pudo registrar el ingreso a guardia.');
        }

        $this->circuito->afterIngreso($model);

        return [
            'id' => (int) $model->id,
            'id_persona' => (int) $model->id_persona,
            'id_efector' => (int) $model->id_efector,
            'circuito_estado' => CircuitoEstado::ESPERA_TRIAGE,
            'estado' => $model->estado,
            'ingreso_at' => $model->ingreso_at,
            'fecha' => $model->fecha,
            'hora' => $model->hora,
        ];
    }
}
