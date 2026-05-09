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
 * @property int|null $legacy_rrhh_servicio_id opcional en BD solo para datos históricos importados
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

    /**
     * Vínculo RRHH–efector (misma persona y efector que esta asignación PES).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhEfector()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_persona' => 'id_persona', 'id_efector' => 'id_efector'])
            ->andOnCondition(['rrhh_efector.deleted_at' => null]);
    }

    public function getAgenda()
    {
        return $this->hasOne(ProfesionalEfectorServicioAgenda::class, ['id_profesional_efector_servicio' => 'id'])
            ->andOnCondition(['profesional_efector_servicio_agenda.deleted_at' => null]);
    }

    /**
     * PES cuya columna `legacy_rrhh_servicio_id` coincide (datos históricos).
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
     * Resuelve id PES: primero si `$id` es PK de PES en ese efector; si no, por `legacy_rrhh_servicio_id`.
     */
    public static function resolveProfesionalEfectorServicioIdFromRrhhServicioId(int $idRrhhServicio, int $idEfector): ?int
    {
        if ($idRrhhServicio <= 0 || $idEfector <= 0) {
            return null;
        }
        $asPes = static::find()
            ->where(['id' => $idRrhhServicio, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($asPes !== null) {
            return (int) $asPes->id;
        }

        return static::findIdByLegacyRrhhServicioId($idRrhhServicio);
    }

    /**
     * @deprecated Sin `rrhh_servicio`: no hay id de slot legacy que derivar del PES.
     */
    public function resolveRrhhServicioAsignadoIdForTurnoCompat(): ?int
    {
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
     * Sin tabla `rrhh_servicio`: no hay id de slot legacy; usar solo {@see findOneActivoPorPersonaEfectorServicio}.
     */
    public static function resolverIdRrhhServicioDesdeRrhhServicioYEfector(int $idRrhh, int $idServicio, int $idEfector): ?int
    {
        return null;
    }

    /**
     * Primer PES asociado a cualquier vínculo RRHH–efector del `id_rr_hh` dado.
     */
    public static function findIdByRrhhEfectorMinLegacyServicio(?int $idRrhh): ?int
    {
        if (!$idRrhh || $idRrhh <= 0) {
            return null;
        }
        $res = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'deleted_at' => null])
            ->orderBy(['id_efector' => SORT_ASC, 'id_persona' => SORT_ASC])
            ->all();
        foreach ($res as $re) {
            $pes = static::find()
                ->where([
                    'id_persona' => (int) $re->id_persona,
                    'id_efector' => (int) $re->id_efector,
                    'deleted_at' => null,
                ])
                ->orderBy(['id' => SORT_ASC])
                ->one();
            if ($pes !== null) {
                return (int) $pes->id;
            }
        }

        return null;
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

        return static::findIdByLegacyRrhhServicioId($idAsignado);
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

