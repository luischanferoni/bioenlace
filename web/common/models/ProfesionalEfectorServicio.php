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
 * @property int|null $legacy_rrhh_servicio_id puente opcional en BD (backfill); el código no lo persiste ni lo exige para resolver PES
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
     * PES vigente para persona + efector + servicio.
     */
    public static function findOneActivoPorPersonaEfectorServicio(int $idPersona, int $idEfector, int $idServicio): ?self
    {
        if ($idPersona <= 0 || $idEfector <= 0 || $idServicio <= 0) {
            return null;
        }

        /** @var self|null $r */
        $r = static::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
                'deleted_at' => null,
            ])
            ->one();

        return $r;
    }

    /**
     * Efectores donde la persona tiene al menos una PES activa (misma forma que {@see RrhhEfector::getEfectores}).
     *
     * @return array<int, array{id_rr_hh:int, id_efector:int, nombre:string, id_localidad:int}>
     */
    public static function getEfectoresParaSesion(int $idPersona): array
    {
        if ($idPersona <= 0) {
            return [];
        }

        $idEfectores = static::find()
            ->select(['id_efector'])
            ->distinct()
            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
            ->column();
        if ($idEfectores === []) {
            return [];
        }

        $out = [];
        foreach ($idEfectores as $idEfector) {
            $idEfector = (int) $idEfector;
            $re = RrhhEfector::find()
                ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
                ->one();
            $idRrhh = $re !== null ? (int) $re->id_rr_hh : 0;
            $ef = Efector::findOne($idEfector);
            $out[] = [
                'id_rr_hh' => $idRrhh,
                'id_efector' => $idEfector,
                'nombre' => $ef !== null ? (string) $ef->nombre : '',
                'id_localidad' => $ef !== null ? (int) $ef->id_localidad : 0,
            ];
        }

        return $out;
    }

    /**
     * @return self[]
     */
    public static function findAllActivosPorServicioEfector(int $idServicio, int $idEfector): array
    {
        if ($idServicio <= 0 || $idEfector <= 0) {
            return [];
        }

        return static::find()
            ->where(['id_servicio' => $idServicio, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->all();
    }

    /**
     * Compat.: `id_rrhh_servicio_asignado` (`rrhh_servicio.id`) → PES en este efector.
     * Prioriza persona+efector+servicio; `legacy_rrhh_servicio_id` en fila PES solo como respaldo en BD.
     */
    public static function resolveProfesionalEfectorServicioIdFromRrhhServicioId(int $idRrhhServicio, int $idEfector): ?int
    {
        if ($idRrhhServicio <= 0 || $idEfector <= 0) {
            return null;
        }
        $rs = RrhhServicio::findOne(['id' => $idRrhhServicio, 'deleted_at' => null]);
        if ($rs !== null) {
            $re = RrhhEfector::find()
                ->where(['id_rr_hh' => $rs->id_rr_hh, 'id_efector' => $idEfector, 'deleted_at' => null])
                ->one();
            if ($re !== null) {
                $idPes = static::findIdByPersonaEfectorServicio((int) $re->id_persona, $idEfector, (int) $rs->id_servicio);
                if ($idPes !== null) {
                    return $idPes;
                }
            }
        }

        return static::findIdByLegacyRrhhServicioId($idRrhhServicio);
    }

    /**
     * `rrhh_servicio.id` para APIs que aún exponen `id_rrhh_servicio_asignado`.
     * Resuelve por persona+efector+servicio; columna `legacy_rrhh_servicio_id` solo si falta vínculo.
     */
    public function resolveRrhhServicioAsignadoIdForTurnoCompat(): ?int
    {
        $re = RrhhEfector::find()
            ->where(['id_persona' => $this->id_persona, 'id_efector' => $this->id_efector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            return $this->legacy_rrhh_servicio_id ? (int) $this->legacy_rrhh_servicio_id : null;
        }
        $rs = RrhhServicio::find()
            ->where(['id_rr_hh' => $re->id_rr_hh, 'id_servicio' => $this->id_servicio, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        if ($rs) {
            return (int) $rs->id;
        }

        return $this->legacy_rrhh_servicio_id ? (int) $this->legacy_rrhh_servicio_id : null;
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
     * Resuelve `rrhh_servicio.id` (slot legacy) desde id_rr_hh + servicio + efector: PES primero, luego tabla legacy.
     */
    public static function resolverIdRrhhServicioDesdeRrhhServicioYEfector(int $idRrhh, int $idServicio, int $idEfector): ?int
    {
        if ($idRrhh <= 0 || $idServicio <= 0 || $idEfector <= 0) {
            return null;
        }
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re !== null) {
            $pes = static::findOneActivoPorPersonaEfectorServicio((int) $re->id_persona, $idEfector, $idServicio);
            if ($pes !== null) {
                $c = $pes->resolveRrhhServicioAsignadoIdForTurnoCompat();
                if ($c !== null) {
                    return (int) $c;
                }
            }
        }
        $legacy = RrhhServicio::obtenerIdRrhhServicio($idRrhh, $idServicio);

        return $legacy ? (int) $legacy : null;
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
        if ($rs === null) {
            return null;
        }
        $res = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'deleted_at' => null])
            ->orderBy(['id_efector' => SORT_ASC, 'id_persona' => SORT_ASC])
            ->all();
        foreach ($res as $re) {
            $idPes = static::findIdByPersonaEfectorServicio((int) $re->id_persona, (int) $re->id_efector, (int) $rs->id_servicio);
            if ($idPes !== null) {
                return $idPes;
            }
        }

        return static::findIdByLegacyRrhhServicioId((int) $rs->id);
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
            $id = static::resolveProfesionalEfectorServicioIdFromRrhhServicioId($idAsignado, $idEfector);
            if ($id !== null) {
                return $id;
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
        $idPes = static::findIdByPersonaEfectorServicio((int) $re->id_persona, $idEfector, (int) $rs->id_servicio);

        return $idPes ?? static::findIdByLegacyRrhhServicioId((int) $rs->id);
    }

    /**
     * Opciones de filtro «Profesional» en listados de turnos (web): mismos servicios que {@see RrhhEfector::obtenerMedicosPorEfector}.
     *
     * @return array<int, array{id:int, datos:string}>
     */
    public static function opcionesProfesionalFiltroTurnosPorEfector(int $idEfector): array
    {
        if ($idEfector <= 0) {
            return [];
        }
        $nombres = [
            'MED CLINICA', 'ODONTOLOGIA', 'PEDIATRIA', 'GINECOLOGIA', 'OBSTETRICIA', 'MED FAMILIAR', 'MED GENERAL',
            'NEUROLOGIA', 'CARDIOLOGIA', 'INMUNOLOGIA CLINICA Y ALERGOLOGIA', 'GASTROENTEROLOGIA', 'OFTALMOLOGIA',
            'ENDOCRINOLOGIA', 'TRAUMATOLOGIA', 'NEUMUNOLOGIA', 'CIRUGIA GENERAL', 'DIABETES', 'GERIATRIA',
            'TERAPIA INTENSIVA', 'PSIQUIATRÍA', 'NEFROLOGÍA', 'UROLOGÍA', 'HEMATOLOGÍA', 'OTORRINOLARINGOLOGIA',
        ];

        return static::find()
            ->alias('pes')
            ->select([
                'id' => 'pes.id',
                'datos' => 'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " ", COALESCE(personas.otro_nombre,""), " - ", servicios.nombre)',
            ])
            ->innerJoin('servicios', 'servicios.id_servicio = pes.id_servicio')
            ->innerJoin('personas', 'personas.id_persona = pes.id_persona')
            ->where(['pes.id_efector' => $idEfector, 'pes.deleted_at' => null])
            ->andWhere(['in', 'servicios.nombre', $nombres])
            ->orderBy(['personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC])
            ->asArray()
            ->all();
    }
}

