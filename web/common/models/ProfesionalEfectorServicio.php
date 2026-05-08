<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use common\models\ProfesionalEfectorServicioAgenda;

/**
 * Asignación operacional: persona/profesional en un efector brindando un servicio.
 *
 * Tabla: `profesional_efector_servicio`
 *
 * Nota: `id_profesional_salud` es opcional por ahora (futura sincronización nacional).
 *
 * @property int $id
 * @property int $id_persona
 * @property int|null $id_profesional_salud
 * @property int $id_efector
 * @property int $id_servicio
 * @property int|null $legacy_rrhh_servicio_id
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class ProfesionalEfectorServicio extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public static function tableName()
    {
        return 'profesional_efector_servicio';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => static function () {
                    return Yii::$app->user && Yii::$app->user->id ? (int) Yii::$app->user->id : null;
                },
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_persona', 'id_efector', 'id_servicio'], 'required'],
            [['id_persona', 'id_profesional_salud', 'id_efector', 'id_servicio', 'legacy_rrhh_servicio_id'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'id_persona']);
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector']);
    }

    public function getServicio()
    {
        return $this->hasOne(Servicio::class, ['id_servicio' => 'id_servicio']);
    }

    public function getAgenda()
    {
        return $this->hasOne(ProfesionalEfectorServicioAgenda::class, ['id_profesional_efector_servicio' => 'id'])
            ->andOnCondition(['profesional_efector_servicio_agenda.deleted_at' => null]);
    }

    /**
     * Resuelve el id de PES vigente a partir del id legacy `rrhh_servicio.id` (transición).
     */
    public static function findIdByLegacyRrhhServicioId(?int $legacyRrhhServicioId): ?int
    {
        if (!$legacyRrhhServicioId) {
            return null;
        }
        $id = static::find()
            ->select(['id'])
            ->where([
                'legacy_rrhh_servicio_id' => $legacyRrhhServicioId,
                'deleted_at' => null,
            ])
            ->scalar();
        return $id !== false && $id !== null ? (int) $id : null;
    }

    /**
     * Para búsqueda de slots sobre agenda legacy (`agenda_rrhh`): id de `rrhh_servicio` coherente con el PES.
     */
    public static function resolveRrhhServicioIdForSlotCriteria(int $idPes, int $idServicio, int $idEfector): ?int
    {
        if ($idPes <= 0 || $idServicio <= 0 || $idEfector <= 0) {
            return null;
        }
        /** @var self|null $pes */
        $pes = static::find()
            ->where([
                'id' => $idPes,
                'id_servicio' => $idServicio,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->one();
        if (!$pes) {
            return null;
        }
        if ($pes->legacy_rrhh_servicio_id) {
            return (int) $pes->legacy_rrhh_servicio_id;
        }
        $re = RrhhEfector::find()
            ->where(['id_persona' => $pes->id_persona, 'id_efector' => $pes->id_efector, 'deleted_at' => null])
            ->one();
        if (!$re) {
            return null;
        }
        $rs = RrhhServicio::find()
            ->where(['id_rr_hh' => $re->id_rr_hh, 'id_servicio' => $pes->id_servicio, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        return $rs ? (int) $rs->id : null;
    }

    /**
     * PES para contexto profesional (id_rr_hh) + efector + servicio, sin usar legacy en la fila consumidora.
     */
    public static function findIdByPersonaEfectorServicio(int $idPersona, int $idEfector, int $idServicio): ?int
    {
        if ($idPersona <= 0 || $idEfector <= 0 || $idServicio <= 0) {
            return null;
        }
        $id = static::find()
            ->select(['id'])
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
                'deleted_at' => null,
            ])
            ->scalar();
        return $id !== false && $id !== null ? (int) $id : null;
    }

    /**
     * Primer `rrhh_servicio` del RRHH (orden por id); útil cuando el consumidor solo guarda `id_rr_hh`.
     */
    public static function findIdByRrhhEfectorMinLegacyServicio(?int $idRrhh): ?int
    {
        if (!$idRrhh || $idRrhh <= 0) {
            return null;
        }
        $rs = RrhhServicio::find()
            ->where(['id_rr_hh' => $idRrhh, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        return $rs ? static::findIdByLegacyRrhhServicioId((int) $rs->id) : null;
    }

    /**
     * Como {@see findIdByRrhhEfectorMinLegacyServicio} pero exige que exista vínculo RRHH–efector coherente.
     */
    public static function findIdByRrhhAndEfectorMinLegacyServicio(?int $idRrhh, ?int $idEfector): ?int
    {
        if (!$idRrhh || $idRrhh <= 0) {
            return null;
        }
        if ($idEfector) {
            $ok = RrhhEfector::find()
                ->where(['id_rr_hh' => $idRrhh, 'id_efector' => $idEfector])
                ->andWhere(['deleted_at' => null])
                ->exists();
            if (!$ok) {
                return null;
            }
        }
        return static::findIdByRrhhEfectorMinLegacyServicio($idRrhh);
    }

    public static function resolvePesIdFromGuardiaAsignado(?int $idAsignado, ?int $idEfector): ?int
    {
        if (!$idAsignado || $idAsignado <= 0) {
            return null;
        }
        if ($idEfector) {
            $rs = RrhhServicio::find()
                ->alias('rs')
                ->innerJoin(
                    '{{%rrhh_efector}} re',
                    're.id_rr_hh = rs.id_rr_hh AND re.id_efector = :ef',
                    [':ef' => $idEfector]
                )
                ->where(['rs.id' => $idAsignado, 'rs.deleted_at' => null, 're.deleted_at' => null])
                ->one();
            if ($rs) {
                $id = static::findIdByLegacyRrhhServicioId((int) $rs->id);
                if ($id !== null) {
                    return $id;
                }
            }
        }
        $fromLegacy = static::findIdByLegacyRrhhServicioId($idAsignado);
        if ($fromLegacy !== null) {
            return $fromLegacy;
        }
        if (!$idEfector) {
            return null;
        }
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idAsignado, 'id_efector' => $idEfector])
            ->andWhere(['deleted_at' => null])
            ->one();
        if (!$re) {
            return null;
        }
        $rs = RrhhServicio::find()
            ->where(['id_rr_hh' => $idAsignado, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        if (!$rs) {
            return null;
        }
        return static::findIdByLegacyRrhhServicioId((int) $rs->id);
    }
}

