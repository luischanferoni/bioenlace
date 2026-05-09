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
            [['id_persona', 'id_profesional_salud', 'id_efector', 'id_servicio'], 'integer'],
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
     * Resuelve id PES cuando el cliente envía la PK de `profesional_efector_servicio` válida en ese efector.
     */
    public static function resolveProfesionalEfectorServicioIdFromRrhhServicioId(int $idCandidate, int $idEfector): ?int
    {
        if ($idCandidate <= 0 || $idEfector <= 0) {
            return null;
        }
        $asPes = static::find()
            ->where(['id' => $idCandidate, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();

        return $asPes !== null ? (int) $asPes->id : null;
    }

    /**
     * PES para contexto profesional (id_rr_hh) + efector + servicio.
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
     * PES a partir de vínculo RRHH + servicio en un efector (sin tabla `rrhh_servicio`).
     */
    public static function resolverIdPesDesdeRrhhServicioYEfector(int $idRrhh, int $idServicio, int $idEfector): ?int
    {
        if ($idRrhh <= 0 || $idServicio <= 0 || $idEfector <= 0) {
            return null;
        }
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            return null;
        }

        return static::findIdByPersonaEfectorServicio((int) $re->id_persona, $idEfector, $idServicio);
    }

    /**
     * Primer PES asociado a cualquier vínculo RRHH–efector del `id_rr_hh` dado.
     */
    public static function findIdByRrhhEfectorMinPes(?int $idRrhh): ?int
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
     * Como {@see findIdByRrhhEfectorMinPes} pero exige vínculo RRHH–efector en el efector indicado.
     */
    public static function findIdByRrhhAndEfectorMinPes(?int $idRrhh, ?int $idEfector): ?int
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

        return static::findIdByRrhhEfectorMinPes($idRrhh);
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

        return static::findIdByRrhhAndEfectorMinPes($idAsignado, $idEfector);
    }

    /**
     * Opciones de filtro «Profesional» en listados de turnos (web): mismos servicios que {@see RrhhEfector::obtenerMedicosPorEfector}.
     *
     * @return array<int, array{id:int, datos:string}>
     */
    /**
     * Resuelve PES desde el campo `id_rrhh` de internación u otros legados: puede ser PK PES o `id_rr_hh`.
     * Sin columna `legacy_rrhh_servicio_id` ni tabla `rrhh_servicio`.
     */
    public static function resolvePesModelFromInternacionRrhhField(int $idLegacy, ?int $idEfectorContext): ?self
    {
        if ($idLegacy <= 0) {
            return null;
        }
        if ($idEfectorContext !== null && $idEfectorContext > 0) {
            $id = static::resolveProfesionalEfectorServicioIdFromRrhhServicioId($idLegacy, $idEfectorContext);
            if ($id !== null) {
                return static::findOne(['id' => $id, 'deleted_at' => null]);
            }
            $id = static::findIdByRrhhAndEfectorMinPes($idLegacy, $idEfectorContext);
            if ($id !== null) {
                return static::findOne(['id' => $id, 'deleted_at' => null]);
            }
        }
        $id = static::findIdByRrhhEfectorMinPes($idLegacy);
        if ($id !== null) {
            return static::findOne(['id' => $id, 'deleted_at' => null]);
        }

        return static::find()->where(['id' => $idLegacy, 'deleted_at' => null])->one();
    }

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
