<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;
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
     * Fila `rr_hh` de la persona (mismo significado operativo que el antiguo vínculo por efector cuando hay un RRHH por persona).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhEfector()
    {
        return $this->hasOne(Rrhh::class, ['id_persona' => 'id_persona'])
            ->orderBy(['id_rr_hh' => SORT_ASC]);
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
     * Efectores donde la persona tiene al menos una PES activa.
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

        $idRrhh = static::resolveIdRrhhForPersona($idPersona);
        $out = [];
        foreach ($idEfectores as $idEfector) {
            $idEfector = (int) $idEfector;
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
        $idPersona = static::resolveIdPersonaFromIdRrhh($idRrhh);
        if ($idPersona === null || $idPersona <= 0) {
            return null;
        }
        if (!static::rrhhTieneAsignacionPesEnEfector($idRrhh, $idEfector)) {
            return null;
        }

        return static::findIdByPersonaEfectorServicio($idPersona, $idEfector, $idServicio);
    }

    /**
     * id_persona para un `id_rr_hh` vía tabla `rr_hh`.
     */
    public static function resolveIdPersonaFromIdRrhh(int $idRrhh): ?int
    {
        if ($idRrhh <= 0) {
            return null;
        }
        $schema = Yii::$app->db->schema->getTableSchema('rr_hh', true);
        if ($schema !== null && isset($schema->columns['id_persona'])) {
            $p = (new Query())->from('rr_hh')->select(['id_persona'])->where(['id_rr_hh' => $idRrhh])->limit(1)->scalar();
            if ($p !== false && $p !== null) {
                return (int) $p;
            }
        }

        return null;
    }

    /**
     * id_rr_hh canónico para la persona (tabla `rr_hh`).
     */
    public static function resolveIdRrhhForPersona(int $idPersona): int
    {
        if ($idPersona <= 0) {
            return 0;
        }
        $schema = Yii::$app->db->schema->getTableSchema('rr_hh', true);
        if ($schema !== null && isset($schema->columns['id_rr_hh'])) {
            $id = (new Query())->from('rr_hh')->select(['id_rr_hh'])->where(['id_persona' => $idPersona])->limit(1)->scalar();
            if ($id !== false && $id !== null) {
                return (int) $id;
            }
        }

        return 0;
    }

    /**
     * El RRHH tiene al menos una asignación PES activa en el efector (sustituye comprobación vía `rrhh_efector`).
     */
    public static function rrhhTieneAsignacionPesEnEfector(int $idRrhh, int $idEfector): bool
    {
        if ($idRrhh <= 0 || $idEfector <= 0) {
            return false;
        }
        $idPersona = static::resolveIdPersonaFromIdRrhh($idRrhh);
        if ($idPersona === null || $idPersona <= 0) {
            return false;
        }

        return static::find()
            ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->exists();
    }

    /**
     * Primer PES asociado al `id_rr_hh` dado (misma persona en cualquier efector con PES).
     */
    public static function findIdByRrhhMinPes(?int $idRrhh): ?int
    {
        if (!$idRrhh || $idRrhh <= 0) {
            return null;
        }
        $idPersona = static::resolveIdPersonaFromIdRrhh((int) $idRrhh);
        if ($idPersona === null || $idPersona <= 0) {
            return null;
        }
        $pes = static::find()
            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
            ->orderBy(['id_efector' => SORT_ASC, 'id' => SORT_ASC])
            ->one();

        return $pes !== null ? (int) $pes->id : null;
    }

    /**
     * Como {@see findIdByRrhhMinPes} pero exige PES en el efector indicado.
     */
    public static function findIdByRrhhAndEfectorMinPes(?int $idRrhh, ?int $idEfector): ?int
    {
        if (!$idRrhh || $idRrhh <= 0) {
            return null;
        }
        $idPersona = static::resolveIdPersonaFromIdRrhh((int) $idRrhh);
        if ($idPersona === null || $idPersona <= 0) {
            return null;
        }
        if ($idEfector) {
            if (!static::rrhhTieneAsignacionPesEnEfector((int) $idRrhh, (int) $idEfector)) {
                return null;
            }
            $pes = static::find()
                ->where([
                    'id_persona' => $idPersona,
                    'id_efector' => (int) $idEfector,
                    'deleted_at' => null,
                ])
                ->orderBy(['id' => SORT_ASC])
                ->one();

            return $pes !== null ? (int) $pes->id : null;
        }

        return static::findIdByRrhhMinPes($idRrhh);
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
        $id = static::findIdByRrhhMinPes($idLegacy);
        if ($id !== null) {
            return static::findOne(['id' => $id, 'deleted_at' => null]);
        }

        return static::find()->where(['id' => $idLegacy, 'deleted_at' => null])->one();
    }

    /**
     * Opciones de filtro «Profesional» en listados de turnos (web).
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

    /**
     * Filas con `id_rr_hh`, `id` (PES), `id_persona`, `datos` para listados de médicos por efector.
     *
     * @return list<array{id_rr_hh:int, id:int, id_persona:int, datos:string}>
     */
    public static function obtenerMedicosPorEfector(int $id_efector): array
    {
        if ($id_efector <= 0) {
            return [];
        }
        $nombres = [
            'MED CLINICA', 'ODONTOLOGIA', 'PEDIATRIA', 'GINECOLOGIA', 'OBSTETRICIA', 'MED FAMILIAR', 'MED GENERAL',
            'NEUROLOGIA', 'CARDIOLOGIA', 'INMUNOLOGIA CLINICA Y ALERGOLOGIA', 'GASTROENTEROLOGIA', 'OFTALMOLOGIA',
            'ENDOCRINOLOGIA', 'TRAUMATOLOGIA', 'NEUMUNOLOGIA', 'CIRUGIA GENERAL', 'DIABETES', 'GERIATRIA',
            'TERAPIA INTENSIVA', 'PSIQUIATRÍA', 'NEFROLOGÍA', 'UROLOGÍA', 'HEMATOLOGÍA', 'OTORRINOLARINGOLOGIA',
        ];
        $rows = static::find()
            ->alias('pes')
            ->select([
                'id' => 'pes.id',
                'id_persona' => 'personas.id_persona',
                'datos' => 'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " " ,COALESCE(personas.otro_nombre,""), " - ", servicios.nombre)',
            ])
            ->where(['pes.id_efector' => $id_efector, 'pes.deleted_at' => null])
            ->innerJoin('servicios', 'servicios.id_servicio = pes.id_servicio')
            ->andWhere(['in', 'servicios.nombre', $nombres])
            ->join('LEFT JOIN', 'personas', 'pes.id_persona = personas.id_persona')
            ->asArray()
            ->all();
        foreach ($rows as &$r) {
            $pid = (int) ($r['id_persona'] ?? 0);
            $r['id_rr_hh'] = $pid > 0 ? static::resolveIdRrhhForPersona($pid) : 0;
        }
        unset($r);

        return $rows;
    }

    /**
     * @return list<array{id_rr_hh:int, id:int, id_persona:int, datos:string}>
     */
    public static function obtenerMedicosPorServicioEfector(int $id_efector, int $id_servicio): array
    {
        if ($id_efector <= 0 || $id_servicio <= 0) {
            return [];
        }
        $rows = static::find()
            ->alias('pes')
            ->select([
                'id' => 'pes.id',
                'id_persona' => 'personas.id_persona',
                'datos' => 'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " " ,COALESCE(personas.otro_nombre,""), " - ", servicios.nombre)',
            ])
            ->where(['pes.id_efector' => $id_efector, 'pes.id_servicio' => $id_servicio, 'pes.deleted_at' => null])
            ->innerJoin('servicios', 'servicios.id_servicio = pes.id_servicio')
            ->join('LEFT JOIN', 'personas', 'pes.id_persona = personas.id_persona')
            ->asArray()
            ->all();
        foreach ($rows as &$r) {
            $pid = (int) ($r['id_persona'] ?? 0);
            $r['id_rr_hh'] = $pid > 0 ? static::resolveIdRrhhForPersona($pid) : 0;
        }
        unset($r);

        return $rows;
    }

    /**
     * Autocomplete de personas con al menos una PES activa en el efector (sustituto de búsqueda vía `rrhh_efector`).
     *
     * @return list<array{id:int|string, text:string}>
     */
    public static function personasConPesLiveSearch(?string $q, int $idEfector): array
    {
        if ($q === null || trim($q) === '' || $idEfector <= 0) {
            return [];
        }
        $qNorm = trim($q);
        $query = (new Query())
            ->select([
                'text' => new Expression('CONCAT(CONCAT(personas.apellido, ", ", personas.nombre), " - ", personas.documento)'),
                'id' => 'personas.id_persona',
            ])
            ->from('personas')
            ->innerJoin(
                'profesional_efector_servicio pes',
                'pes.id_persona = personas.id_persona AND pes.id_efector = :ef AND pes.deleted_at IS NULL',
                [':ef' => $idEfector]
            )
            ->andWhere([
                'or',
                ['like', new Expression('CONCAT(personas.apellido," ",personas.nombre)'), '%' . $qNorm . '%', false],
                ['like', 'personas.nombre', '%' . $qNorm . '%', false],
                ['like', 'personas.apellido', $qNorm . '%', false],
                ['like', 'personas.documento', $qNorm . '%', false],
            ])
            ->groupBy(['personas.id_persona', 'personas.apellido', 'personas.nombre', 'personas.documento'])
            ->limit(20);

        return array_values($query->all());
    }
}
