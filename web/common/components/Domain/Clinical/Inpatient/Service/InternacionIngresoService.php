<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\components\Domain\Clinical\Service\CarePlanLifecycleService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaInternacionService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\models\CoberturaMedica;
use common\models\Efector;
use common\models\Guardia;
use common\models\InfraestructuraCama;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionHcama;
use common\models\SegNivelInternacionRepository;
use common\components\Domain\Integrations\Mpi\MpiApiClient;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Ingreso de paciente a internación (cama + episodio).
 */
final class InternacionIngresoService
{
    public const MSG_SIN_CAMAS_DISPONIBLES =
        'No hay camas desocupadas en este efector. Configure infraestructura (piso/sala/cama) o libere una cama.';

    /**
     * @return array<string, mixed>
     */
    public function contextoIngreso(
        int $idPersona,
        int $idEfector,
        ?int $idCama = null,
        ?int $idGuardia = null
    ): array {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('Se requiere id_persona.');
        }
        $this->assertIngresoEfector($idEfector);

        if (SegNivelInternacion::personaInternada($idPersona)) {
            throw new \InvalidArgumentException('El paciente ya tiene una internación activa.');
        }

        $persona = Persona::findOne($idPersona);
        if ($persona === null) {
            throw new \InvalidArgumentException('Paciente no encontrado.');
        }

        $camaLabel = '';
        $idCamaResolved = $idCama;
        if ($idCamaResolved !== null && $idCamaResolved > 0) {
            $cama = InfraestructuraCama::findOne($idCamaResolved);
            if ($cama === null) {
                throw new \InvalidArgumentException('Cama no encontrada.');
            }
            InternacionEfectorAccess::assertCamaEnEfector($cama, $idEfector);
            if (strtolower((string) $cama->estado) !== 'desocupada') {
                throw new \InvalidArgumentException('La cama seleccionada no está disponible.');
            }
            $label = SegNivelInternacionHcama::getCamaActualLabel($idCamaResolved);
            $camaLabel = (string) ($label['label'] ?? '');
        }

        $guardiaCtx = $this->resolveGuardiaPrefill($idGuardia, $idEfector);

        $nombre = method_exists($persona, 'getNombreCompleto')
            ? $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
            : trim($persona->nombre . ' ' . $persona->apellido);

        $camaSugerencias = (new InternacionCamaSugerenciaAgent())->suggestForIngreso(
            $idEfector,
            $idPersona,
            $guardiaCtx['id_guardia']
        );

        $fromGuardia = $guardiaCtx['id_guardia'] !== null;
        $camas = $this->camasOptions($idEfector);
        $profesionales = $this->profesionalesOptions($idEfector);
        $pesSesion = $this->resolvePesSesionEnLista($profesionales);

        return [
            'id_persona' => $idPersona,
            'paciente_nombre' => $nombre,
            'paciente_documento' => (string) ($persona->documento ?? ''),
            'id_cama' => $idCamaResolved,
            'cama_label' => $camaLabel,
            'cama_sugerencias' => $camaSugerencias,
            'camas_aviso' => $camas === []
                ? self::MSG_SIN_CAMAS_DISPONIBLES
                : '',
            'puede_ingresar' => $camas !== [],
            'id_guardia' => $guardiaCtx['id_guardia'],
            'id_tipo_ingreso_default' => $fromGuardia
                ? ($guardiaCtx['id_tipo_ingreso'] ?? 1)
                : $guardiaCtx['id_tipo_ingreso'],
            'id_profesional_efector_servicio_default' => $pesSesion,
            'condiciones_derivacion' => $guardiaCtx['condiciones_derivacion'],
            'obra_social_default' => $this->resolveObraSocialDefault($persona),
            'fecha_inicio' => date('Y-m-d'),
            'hora_inicio' => date('H:i'),
            'profesionales' => $profesionales,
            'camas_disponibles' => $camas,
            'coberturas' => $this->coberturasOptions($persona),
            // Derivación: no volcar el catálogo provincial en el flujo desde guardia.
            'efectores_origen' => $fromGuardia ? [] : $this->efectoresOptions(),
            'tipos_ingreso' => $this->tiposIngresoOptions(),
            'ingresa_en' => $this->enumOptions(SegNivelInternacion::INGRESO_EN),
            'ingresa_con' => $this->enumOptions(SegNivelInternacion::INGRESO_CON),
            'desde_guardia' => $fromGuardia,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function registrarIngreso(int $idEfector, array $post): array
    {
        $this->assertIngresoEfector($idEfector);

        $idPersona = (int) ($post['id_persona'] ?? 0);
        $idCama = (int) ($post['id_cama'] ?? 0);
        if ($idPersona <= 0 || $idCama <= 0) {
            throw new \InvalidArgumentException('Paciente y cama son obligatorios.');
        }

        $this->assertCamaDisponibleParaIngreso($idEfector, $idCama);

        $this->contextoIngreso(
            $idPersona,
            $idEfector,
            $idCama,
            (int) ($post['id_guardia'] ?? 0) ?: null
        );

        $model = new SegNivelInternacion();
        $model->scenario = SegNivelInternacion::INGRESO_PACIENTE;
        $model->id_persona = $idPersona;
        $model->id_cama = $idCama;
        $idGuardia = (int) ($post['id_guardia'] ?? 0);
        $idTipoIngreso = (int) ($post['id_tipo_ingreso'] ?? 0);
        if ($idTipoIngreso <= 0 && $idGuardia > 0) {
            $idTipoIngreso = 1; // Guardia
        }

        $model->id_profesional_efector_servicio = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        $model->id_tipo_ingreso = $idTipoIngreso;
        $model->id_efector_origen = !empty($post['id_efector_origen'])
            ? (int) $post['id_efector_origen']
            : null;
        $model->ingresa_en = trim((string) ($post['ingresa_en'] ?? ''));
        $model->ingresa_con = trim((string) ($post['ingresa_con'] ?? ''));
        $model->datos_contacto_nombre = trim((string) ($post['datos_contacto_nombre'] ?? ''));
        $model->situacion_al_ingresar = trim((string) ($post['situacion_al_ingresar'] ?? ''));
        $model->obra_social = !empty($post['obra_social']) ? (int) $post['obra_social'] : null;
        $fechaRaw = trim((string) ($post['fecha_inicio'] ?? ''));
        $horaRaw = trim((string) ($post['hora_inicio'] ?? ''));
        $model->fecha_inicio = $this->normalizeFechaInicio($fechaRaw !== '' ? $fechaRaw : date('Y-m-d'));
        $model->hora_inicio = $horaRaw !== '' ? $horaRaw : date('H:i');

        if ($idGuardia > 0) {
            $model->id_guardia = $idGuardia;
        }
        if (!empty($post['condiciones_derivacion'])) {
            $model->condiciones_derivacion = trim((string) $post['condiciones_derivacion']);
        }

        $requiereContacto = in_array($model->ingresa_con, ['familiar', 'otro', 'policia'], true);
        if ($requiereContacto) {
            $tel = trim((string) ($post['datos_contacto_tel'] ?? ''));
            if ($tel === '') {
                throw new \InvalidArgumentException('Indique teléfono de contacto del acompañante.');
            }
            $model->datos_contacto_tel = $tel;
        } else {
            $model->datos_contacto_tel = '';
        }

        $cama = InfraestructuraCama::findOne($idCama);
        if ($cama === null) {
            throw new \InvalidArgumentException('Cama no encontrada.');
        }
        $cama->estado = 'ocupada';

        if (!$model->validate() || !$cama->validate()) {
            $errors = array_merge($model->getFirstErrors(), $cama->getFirstErrors());
            $first = reset($errors);
            throw new \InvalidArgumentException($first !== false ? (string) $first : 'Datos de ingreso inválidos.');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$model->save(false)) {
                throw new \RuntimeException('No se pudo guardar la internación.');
            }
            if (!$cama->save(false)) {
                throw new \RuntimeException('No se pudo actualizar el estado de la cama.');
            }
            SegNivelInternacionRepository::doAgregarHistoriaCama($model);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        try {
            (new CarePlanLifecycleService())->onInternacionAdmission($model);
        } catch (\Throwable $e) {
            Yii::error(
                'CarePlanLifecycle tras ingreso internación #' . $model->id . ': ' . $e->getMessage(),
                __METHOD__
            );
        }

        if ($idGuardia > 0) {
            (new GuardiaInternacionService())->marcarInternacionDesdeGuardia($idGuardia, (int) $model->id);
        }

        return [
            'internacion_id' => (int) $model->id,
            'id_persona' => $idPersona,
            'id_cama' => $idCama,
            'message' => 'Ingreso registrado con éxito.',
        ];
    }

    private function normalizeFechaInicio(string $fecha): string
    {
        if ($fecha === '') {
            return date('d/m/Y');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $fecha);

            return $dt ? $dt->format('d/m/Y') : date('d/m/Y');
        }

        return $fecha;
    }

    /**
     * @return array{id_guardia: int|null, id_tipo_ingreso: int|null, condiciones_derivacion: string|null}
     */
    private function resolveGuardiaPrefill(?int $idGuardia, int $idEfector): array
    {
        if ($idGuardia === null || $idGuardia <= 0) {
            return ['id_guardia' => null, 'id_tipo_ingreso' => null, 'condiciones_derivacion' => null];
        }
        $guardia = Guardia::findOne($idGuardia);
        if ($guardia === null || (int) $guardia->id_efector !== $idEfector) {
            return ['id_guardia' => null, 'id_tipo_ingreso' => null, 'condiciones_derivacion' => null];
        }

        return [
            'id_guardia' => $idGuardia,
            'id_tipo_ingreso' => 1,
            'condiciones_derivacion' => !empty($guardia->condiciones_derivacion)
                ? (string) $guardia->condiciones_derivacion
                : null,
        ];
    }

    private function resolveObraSocialDefault(Persona $persona): ?int
    {
        try {
            $sexoMap = ['m' => 0, 'f' => 1];
            $personaSexo = ArrayHelper::getValue($sexoMap, strtolower((string) $persona->sexo), 0);
            $mpi = new MpiApiClient();
            $coberturasApi = $mpi->get_cobertura_social((string) $persona->documento, $personaSexo);
            if (count($coberturasApi) === 1) {
                return (int) ($coberturasApi[0]['codigo'] ?? 0) ?: null;
            }
        } catch (\Throwable $e) {
            Yii::warning('MPI cobertura ingreso internación: ' . $e->getMessage(), __METHOD__);
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function coberturasOptions(Persona $persona): array
    {
        $filtro = null;
        try {
            $sexoMap = ['m' => 0, 'f' => 1];
            $personaSexo = ArrayHelper::getValue($sexoMap, strtolower((string) $persona->sexo), 0);
            $mpi = new MpiApiClient();
            $coberturasApi = $mpi->get_cobertura_social((string) $persona->documento, $personaSexo);
            if ($coberturasApi !== []) {
                $filtro = ArrayHelper::getColumn($coberturasApi, 'codigo');
            }
        } catch (\Throwable $e) {
            Yii::warning('MPI cobertura ingreso internación: ' . $e->getMessage(), __METHOD__);
        }

        $rows = CoberturaMedica::getCoberturasForSelect($filtro);
        $options = [];
        foreach ($rows as $row) {
            $options[] = [
                'value' => (string) $row->codigo,
                'label' => (string) $row->nombre,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function profesionalesOptions(int $idEfector): array
    {
        $profesionales = ProfesionalEfectorServicio::obtenerMedicosPorEfector($idEfector);
        $seenPersona = [];
        $options = [];
        foreach ($profesionales as $pes) {
            $idPersonaPes = (int) ($pes['id_persona'] ?? 0);
            // Varios PES demo de la misma persona en el mismo servicio: una opción por persona.
            if ($idPersonaPes > 0 && isset($seenPersona[$idPersonaPes])) {
                continue;
            }
            if ($idPersonaPes > 0) {
                $seenPersona[$idPersonaPes] = true;
            }
            $options[] = [
                'value' => (string) ($pes['id'] ?? ''),
                'label' => (string) ($pes['datos'] ?? ''),
            ];
        }

        $pesSesion = $this->resolvePesSesionEnLista($options);
        if ($pesSesion === null) {
            return $options;
        }

        usort($options, static function (array $a, array $b) use ($pesSesion): int {
            $aMatch = (string) ($a['value'] ?? '') === $pesSesion ? 0 : 1;
            $bMatch = (string) ($b['value'] ?? '') === $pesSesion ? 0 : 1;

            return $aMatch <=> $bMatch;
        });

        return $options;
    }

    /**
     * @param list<array{value: string, label: string}> $options
     */
    private function resolvePesSesionEnLista(array $options): ?string
    {
        try {
            $pesSesion = (int) Yii::$app->user->getIdProfesionalEfectorServicio();
        } catch (\Throwable $e) {
            return null;
        }
        if ($pesSesion <= 0) {
            return null;
        }
        $value = (string) $pesSesion;
        foreach ($options as $opt) {
            if ((string) ($opt['value'] ?? '') === $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function listarCamasDisponibles(int $idEfector): array
    {
        return $this->camasOptions($idEfector);
    }

    public function hayCamasDisponibles(int $idEfector): bool
    {
        return $this->camasOptions($idEfector) !== [];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function camasOptions(int $idEfector): array
    {
        $camas = SegNivelInternacionHcama::getCamasDisponiblesForSelect($idEfector);

        return array_map(static fn (array $row): array => [
            'value' => (string) ($row['code'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
        ], $camas);
    }

    /**
     * Gate hard: no se registra ingreso sin cama desocupada del efector.
     */
    private function assertCamaDisponibleParaIngreso(int $idEfector, int $idCama): void
    {
        $disponibles = $this->camasOptions($idEfector);
        if ($disponibles === []) {
            throw new \InvalidArgumentException(self::MSG_SIN_CAMAS_DISPONIBLES);
        }

        $idCamaStr = (string) $idCama;
        foreach ($disponibles as $opt) {
            if ((string) ($opt['value'] ?? '') === $idCamaStr) {
                return;
            }
        }

        throw new \InvalidArgumentException(
            'La cama seleccionada no está disponible (ocupada o no pertenece a este efector).'
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function efectoresOptions(): array
    {
        $options = [];
        foreach (Efector::find()->orderBy(['nombre' => SORT_ASC])->all() as $efector) {
            $options[] = [
                'value' => (string) $efector->id_efector,
                'label' => (string) $efector->nombre,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function tiposIngresoOptions(): array
    {
        $options = [];
        foreach (SegNivelInternacion::TIPO_INGRESO as $id => $label) {
            $options[] = ['value' => (string) $id, 'label' => (string) $label];
        }

        return $options;
    }

    /**
     * @param array<string, string> $map
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $options;
    }

    private function assertIngresoEfector(int $idEfector): void
    {
        try {
            EfectorAccessService::assertAndResolveIdEfector('Internacion.create', ['id_efector' => $idEfector]);
        } catch (DomainOperationForbiddenException $e) {
            throw new \InvalidArgumentException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.', 0, $e);
        }
    }
}
