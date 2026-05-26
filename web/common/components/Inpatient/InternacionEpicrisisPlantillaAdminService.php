<?php

namespace common\components\Inpatient;

use common\models\InternacionEpicrisisPlantilla;
use common\models\Servicio;
use common\models\ServiciosEfector;
use Yii;
use yii\db\Query;

/**
 * ABM de plantillas de epicrisis (staff por efector).
 */
final class InternacionEpicrisisPlantillaAdminService
{
    /** @var list<string> */
    public const PLACEHOLDERS = [
        '{paciente}',
        '{fecha_ingreso}',
        '{dias_internacion}',
        '{documento}',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAdmin(int $idEfector, bool $incluirInactivas = true): array
    {
        InternacionEfectorAccess::assertCanAccessEfector($idEfector);

        $q = (new Query())
            ->from(['p' => InternacionEpicrisisPlantilla::tableName()])
            ->where([
                'or',
                ['p.id_efector' => $idEfector],
                ['p.id_efector' => 0],
            ]);

        if (!$incluirInactivas) {
            $q->andWhere(['p.activo' => 1]);
        }

        $rows = $q->orderBy(['p.id_efector' => SORT_ASC, 'p.orden' => SORT_ASC, 'p.nombre' => SORT_ASC])->all();
        $isSuperadmin = (bool) (Yii::$app->user->isSuperadmin ?? false);

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->rowToArray($row, $idEfector, $isSuperadmin);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function obtener(int $id, int $idEfector): array
    {
        InternacionEfectorAccess::assertCanAccessEfector($idEfector);
        $row = $this->findRow($id);
        if ($row === null) {
            throw new \InvalidArgumentException('Plantilla no encontrada.');
        }
        $this->assertVisibleForEfector($row, $idEfector);

        return $this->rowToArray(
            $row,
            $idEfector,
            (bool) (Yii::$app->user->isSuperadmin ?? false)
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function crear(array $payload, int $idEfector, bool $isSuperadmin): array
    {
        InternacionEfectorAccess::assertCanAccessEfector($idEfector);

        $targetEfector = $this->resolveTargetEfectorOnCreate($payload, $idEfector, $isSuperadmin);
        $model = new InternacionEpicrisisPlantilla();
        $model->id_efector = $targetEfector;
        $this->applyPayload($model, $payload, $targetEfector);

        if (!$model->save()) {
            throw new \InvalidArgumentException($this->firstError($model));
        }

        return $this->obtener((int) $model->id, $idEfector);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function actualizar(int $id, array $payload, int $idEfector, bool $isSuperadmin): array
    {
        InternacionEfectorAccess::assertCanAccessEfector($idEfector);
        $model = $this->findModel($id);
        $this->assertCanManage($model, $idEfector, $isSuperadmin);
        $this->applyPayload($model, $payload, (int) $model->id_efector);

        if (!$model->save()) {
            throw new \InvalidArgumentException($this->firstError($model));
        }

        return $this->obtener((int) $model->id, $idEfector);
    }

    public function desactivar(int $id, int $idEfector, bool $isSuperadmin): void
    {
        $this->setActivo($id, $idEfector, $isSuperadmin, false);
    }

    public function activar(int $id, int $idEfector, bool $isSuperadmin): void
    {
        $this->setActivo($id, $idEfector, $isSuperadmin, true);
    }

    private function setActivo(int $id, int $idEfector, bool $isSuperadmin, bool $activo): void
    {
        InternacionEfectorAccess::assertCanAccessEfector($idEfector);
        $model = $this->findModel($id);
        $this->assertCanManage($model, $idEfector, $isSuperadmin);
        $model->activo = $activo;
        $model->updated_at = time();
        if (!$model->save(false, ['activo', 'updated_at'])) {
            throw new \InvalidArgumentException('No se pudo actualizar el estado de la plantilla.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(InternacionEpicrisisPlantilla $model, array $payload, int $idEfector): void
    {
        if (array_key_exists('nombre', $payload)) {
            $model->nombre = trim((string) $payload['nombre']);
        }
        if (array_key_exists('cuerpo', $payload)) {
            $model->cuerpo = (string) $payload['cuerpo'];
        }
        if (array_key_exists('orden', $payload)) {
            $model->orden = (int) $payload['orden'];
        }
        if (array_key_exists('activo', $payload)) {
            $model->activo = filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('id_servicio', $payload)) {
            $raw = $payload['id_servicio'];
            $model->id_servicio = ($raw === null || $raw === '' || (int) $raw <= 0) ? null : (int) $raw;
        }

        $this->assertServicioDelEfector($model->id_servicio, $idEfector);

        $now = time();
        if ($model->isNewRecord) {
            $model->created_at = $now;
            if (!array_key_exists('activo', $payload)) {
                $model->activo = true;
            }
            if (!array_key_exists('orden', $payload)) {
                $model->orden = 0;
            }
        }
        $model->updated_at = $now;
    }

    private function assertServicioDelEfector(?int $idServicio, int $idEfector): void
    {
        if ($idServicio === null || $idServicio <= 0 || $idEfector <= 0) {
            return;
        }
        $exists = ServiciosEfector::find()
            ->where(['id_efector' => $idEfector, 'id_servicio' => $idServicio])
            ->exists();
        if (!$exists) {
            throw new \InvalidArgumentException('El servicio indicado no pertenece al efector.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveTargetEfectorOnCreate(array $payload, int $idEfector, bool $isSuperadmin): int
    {
        $requested = isset($payload['id_efector']) ? (int) $payload['id_efector'] : $idEfector;
        if ($requested === 0) {
            if (!$isSuperadmin) {
                throw new \InvalidArgumentException('Solo un superadministrador puede crear plantillas globales.');
            }

            return 0;
        }
        if ($requested !== $idEfector) {
            InternacionEfectorAccess::assertCanAccessEfector($requested);
        }

        return $requested;
    }

    private function assertVisibleForEfector(array $row, int $idEfector): void
    {
        $ef = (int) ($row['id_efector'] ?? -1);
        if ($ef === 0 || $ef === $idEfector) {
            return;
        }
        throw new \InvalidArgumentException('Plantilla no disponible para este efector.');
    }

    private function assertCanManage(InternacionEpicrisisPlantilla $model, int $idEfector, bool $isSuperadmin): void
    {
        $ef = (int) $model->id_efector;
        if ($ef === $idEfector) {
            return;
        }
        if ($ef === 0 && $isSuperadmin) {
            return;
        }
        if ($ef === 0) {
            throw new \InvalidArgumentException('Las plantillas globales solo pueden editarlas un superadministrador.');
        }
        throw new \InvalidArgumentException('No puede modificar plantillas de otro efector.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function rowToArray(array $row, int $idEfector, bool $isSuperadmin): array
    {
        $ef = (int) ($row['id_efector'] ?? 0);
        $idServicio = $row['id_servicio'] !== null ? (int) $row['id_servicio'] : null;

        return [
            'id' => (int) $row['id'],
            'nombre' => (string) $row['nombre'],
            'cuerpo' => (string) $row['cuerpo'],
            'id_efector' => $ef,
            'id_servicio' => $idServicio,
            'servicio_nombre' => $this->servicioLabel($idServicio),
            'activo' => (bool) ($row['activo'] ?? true),
            'orden' => (int) ($row['orden'] ?? 0),
            'es_global' => $ef === 0,
            'editable' => $ef === $idEfector || ($ef === 0 && $isSuperadmin),
            'created_at' => isset($row['created_at']) ? (int) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (int) $row['updated_at'] : null,
        ];
    }

    private function servicioLabel(?int $idServicio): ?string
    {
        if ($idServicio === null || $idServicio <= 0) {
            return null;
        }
        $s = Servicio::findOne($idServicio);

        return $s ? (string) ($s->descripcion ?? $s->nombre ?? ('Servicio #' . $idServicio)) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(int $id): ?array
    {
        $row = (new Query())
            ->from(InternacionEpicrisisPlantilla::tableName())
            ->where(['id' => $id])
            ->one();

        return is_array($row) ? $row : null;
    }

    private function findModel(int $id): InternacionEpicrisisPlantilla
    {
        $model = InternacionEpicrisisPlantilla::findOne($id);
        if ($model === null) {
            throw new \InvalidArgumentException('Plantilla no encontrada.');
        }

        return $model;
    }

    private function firstError(InternacionEpicrisisPlantilla $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'No se pudo guardar la plantilla.';
    }
}
