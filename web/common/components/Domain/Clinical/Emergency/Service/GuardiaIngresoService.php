<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Domain\Person\Service\PersonaAltaOperativaService;
use common\components\Domain\Person\Service\PersonaBusquedaAsistenteUiService;
use common\models\Guardia;
use common\models\InfraestructuraCama;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraSala;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\SegNivelInternacion;
use Yii;
use yii\db\Query;

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
        $idPersona = $this->resolverIdPersona($body);

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

    /**
     * Paciente conocido (`id_persona`) o alta mínima (apellido/nombre/documento/fecha/sexo).
     *
     * @param array<string, mixed> $body
     */
    private function resolverIdPersona(array $body): int
    {
        $idPersona = (int) ($body['id_persona'] ?? 0);
        if ($idPersona > 0) {
            return $idPersona;
        }

        $alta = new PersonaAltaOperativaService();
        if (!$alta->pareceAlta($body)) {
            throw new \InvalidArgumentException(
                'Elegí un paciente de la búsqueda o registrá uno nuevo (apellido, nombre, documento, fecha de nacimiento y sexo).'
            );
        }

        return (int) $alta->crearOReusar($body)->id_persona;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function opcionesIngresaEn(): array
    {
        return $this->mapConstOptions(Guardia::INGRESO_EN);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function opcionesIngresaCon(): array
    {
        return $this->mapConstOptions(Guardia::INGRESO_CON);
    }

    /**
     * Pacientes para admisión: búsqueda por q, o conocidos del efector si q vacío.
     * Excluye quienes ya están en guardia pendiente de este efector.
     *
     * @return list<array{value: string, label: string}>
     */
    public function buscarCandidatos(int $idEfector, ?string $q, int $limit = 30): array
    {
        if ($idEfector <= 0) {
            return [];
        }
        if ($limit < 1) {
            $limit = 30;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $pendientes = $this->idsPersonaGuardiaPendiente($idEfector);
        $q = $q !== null ? trim($q) : '';
        if ($q !== '') {
            $items = PersonaBusquedaAsistenteUiService::buscar($q, $limit);
            $out = [];
            foreach ($items as $it) {
                $id = (int) ($it['id'] ?? 0);
                if ($id <= 0 || isset($pendientes[$id])) {
                    continue;
                }
                $out[] = [
                    'value' => (string) $id,
                    'label' => (string) ($it['name'] ?? ''),
                ];
            }

            return $out;
        }

        $ids = $this->idsPersonaConocidasEnEfector($idEfector, $pendientes, $limit);
        if ($ids === []) {
            return [];
        }

        /** @var Persona[] $personas */
        $personas = Persona::find()
            ->where(['id_persona' => $ids])
            ->orderBy(['apellido' => SORT_ASC, 'nombre' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($personas as $persona) {
            $out[] = [
                'value' => (string) (int) $persona->id_persona,
                'label' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
            ];
        }

        return $out;
    }

    /**
     * @param array<int|string, string> $map
     * @return list<array{value: string, label: string}>
     */
    private function mapConstOptions(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, true>
     */
    private function idsPersonaGuardiaPendiente(int $idEfector): array
    {
        $ids = Guardia::find()
            ->select('id_persona')
            ->andWhere([
                'id_efector' => $idEfector,
                'estado' => Guardia::ESTADO_PENDIENTE,
            ])
            ->column();
        $set = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $set[$id] = true;
            }
        }

        return $set;
    }

    /**
     * @param array<int, true> $excluir
     * @return list<int>
     */
    private function idsPersonaConocidasEnEfector(int $idEfector, array $excluir, int $limit): array
    {
        $guardiaIds = Guardia::find()
            ->select('id_persona')
            ->andWhere(['id_efector' => $idEfector])
            ->column();
        $turnoIds = (new Query())
            ->select('id_persona')
            ->from(Turno::tableName())
            ->where(['id_efector' => $idEfector])
            ->column();
        $internacionIds = (new Query())
            ->select('i.id_persona')
            ->from(['i' => SegNivelInternacion::tableName()])
            ->innerJoin(['cama' => InfraestructuraCama::tableName()], 'cama.id = i.id_cama')
            ->innerJoin(['sala' => InfraestructuraSala::tableName()], 'sala.id = cama.id_sala')
            ->innerJoin(['piso' => InfraestructuraPiso::tableName()], 'piso.id = sala.id_piso')
            ->where(['piso.id_efector' => $idEfector])
            ->column();

        $out = [];
        foreach (array_merge($guardiaIds, $turnoIds, $internacionIds) as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($excluir[$id]) || isset($out[$id])) {
                continue;
            }
            $out[$id] = $id;
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_values($out);
    }
}
