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
     * @return array<int, array{id_profesional_efector_servicio:int, id_efector:int, nombre:string, id_localidad:int}>
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
            $ef = Efector::findOne($idEfector);
            $firstPesId = static::find()
                ->select(['id'])
                ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
                ->orderBy(['id' => SORT_ASC])
                ->scalar();
            $out[] = [
                'id_profesional_efector_servicio' => $firstPesId !== false && $firstPesId !== null ? (int) $firstPesId : 0,
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
     * Resuelve id PES cuando el valor es la PK de `profesional_efector_servicio` en ese efector.
     */
    public static function resolvePesIdFromPkEnEfector(int $idCandidate, int $idEfector): ?int
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
     * Primer id PES de la persona (cualquier efector), para bootstrap de sesión/JWT legado numérico.
     */
    public static function firstPesIdForPersona(int $idPersona): int
    {
        if ($idPersona <= 0) {
            return 0;
        }
        $id = static::find()
            ->select(['id'])
            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
            ->orderBy(['id_efector' => SORT_ASC, 'id' => SORT_ASC])
            ->scalar();

        return $id !== false && $id !== null ? (int) $id : 0;
    }

    /**
     * Identificador de asignación profesional (PK PES) desde query/body.
     *
     * @param array<string, mixed> $params query/body mezclados
     */
    public static function staffContextIdFromRequestParams(array $params): int
    {
        $pes = isset($params['id_profesional_efector_servicio']) ? (int) $params['id_profesional_efector_servicio'] : 0;

        return $pes > 0 ? $pes : 0;
    }

    /**
     * @see staffContextIdFromRequestParams
     */
    public static function staffContextIdFromRequest(\yii\web\Request $request): int
    {
        return static::staffContextIdFromRequestParams(array_merge($request->get(), $request->post()));
    }

    /**
     * id_persona desde un id de contexto staff: PK de {@see self} (PES).
     */
    public static function resolveIdPersonaFromStaffContextId(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }
        $pes = static::findOne(['id' => $id, 'deleted_at' => null]);

        return $pes !== null ? (int) $pes->id_persona : null;
    }

    /**
     * PES para persona + efector + servicio.
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
     * PES para persona + servicio en efector; `idCandidato` es PK PES o id_persona (se intenta PES primero).
     */
    public static function resolverIdPesDesdePersonaServicioYEfector(int $idCandidato, int $idServicio, int $idEfector): ?int
    {
        if ($idCandidato <= 0 || $idServicio <= 0 || $idEfector <= 0) {
            return null;
        }
        $pesDirecto = static::findOne([
            'id' => $idCandidato,
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
            'deleted_at' => null,
        ]);
        if ($pesDirecto !== null) {
            return (int) $pesDirecto->id;
        }
        $idPersona = static::resolveIdPersonaFromStaffContextId($idCandidato);
        if ($idPersona === null || $idPersona <= 0) {
            $idPersona = Persona::find()->where(['id_persona' => $idCandidato])->exists() ? $idCandidato : null;
        }
        if ($idPersona === null || $idPersona <= 0) {
            return null;
        }

        return static::findIdByPersonaEfectorServicio($idPersona, $idEfector, $idServicio);
    }

    /**
     * La persona tiene al menos una fila PES activa en el efector.
     */
    public static function personaTienePesEnEfector(int $idPersona, int $idEfector): bool
    {
        if ($idPersona <= 0 || $idEfector <= 0) {
            return false;
        }

        return static::find()
            ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->exists();
    }

    /**
     * Contexto staff numérico (habitualmente PK PES en sesión): ¿hay PES en el efector?
     */
    public static function staffContextTienePesEnEfector(int $staffContextId, int $idEfector): bool
    {
        $idPersona = static::resolveIdPersonaFromStaffContextId($staffContextId);
        if ($idPersona === null || $idPersona <= 0) {
            return false;
        }

        return static::personaTienePesEnEfector($idPersona, $idEfector);
    }

    /**
     * Primer PES por id PK o, si no existe fila, por id_persona con cualquier PES.
     */
    public static function findFirstPesIdByStaffOrPersona(?int $idCandidato): ?int
    {
        if (!$idCandidato || $idCandidato <= 0) {
            return null;
        }
        $pes = static::findOne(['id' => $idCandidato, 'deleted_at' => null]);
        if ($pes !== null) {
            return (int) $pes->id;
        }
        $idPersona = $idCandidato;
        if (!Persona::find()->where(['id_persona' => $idPersona])->exists()) {
            return null;
        }
        $pes = static::find()
            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
            ->orderBy(['id_efector' => SORT_ASC, 'id' => SORT_ASC])
            ->one();

        return $pes !== null ? (int) $pes->id : null;
    }

    /**
     * PES en efector: por PK PES en ese efector o por persona + efector (primer id).
     */
    public static function findFirstPesIdInEfector(?int $idCandidato, ?int $idEfector): ?int
    {
        if (!$idCandidato || $idCandidato <= 0) {
            return null;
        }
        if ($idEfector !== null && (int) $idEfector > 0) {
            $pes = static::findOne([
                'id' => $idCandidato,
                'id_efector' => (int) $idEfector,
                'deleted_at' => null,
            ]);
            if ($pes !== null) {
                return (int) $pes->id;
            }
            $idPersona = static::resolveIdPersonaFromStaffContextId((int) $idCandidato);
            if ($idPersona === null || $idPersona <= 0) {
                $idPersona = Persona::find()->where(['id_persona' => $idCandidato])->exists() ? (int) $idCandidato : null;
            }
            if ($idPersona !== null && $idPersona > 0 && static::personaTienePesEnEfector($idPersona, (int) $idEfector)) {
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

            return null;
        }

        return static::findFirstPesIdByStaffOrPersona($idCandidato);
    }

    public static function resolvePesIdFromGuardiaAsignado(?int $idAsignado, ?int $idEfector): ?int
    {
        if (!$idAsignado || $idAsignado <= 0) {
            return null;
        }
        if ($idEfector) {
            $id = static::resolvePesIdFromPkEnEfector($idAsignado, $idEfector);
            if ($id !== null) {
                return $id;
            }
        }

        return static::findFirstPesIdInEfector($idAsignado, $idEfector);
    }

    /**
     * Campo legado numérico: PK PES o persona con PES en el efector de contexto.
     */
    public static function resolvePesModelFromInternacionLegacyField(int $idLegacy, ?int $idEfectorContext): ?self
    {
        if ($idLegacy <= 0) {
            return null;
        }
        if ($idEfectorContext !== null && $idEfectorContext > 0) {
            $id = static::resolvePesIdFromPkEnEfector($idLegacy, $idEfectorContext);
            if ($id !== null) {
                return static::findOne(['id' => $id, 'deleted_at' => null]);
            }
            $id = static::findFirstPesIdInEfector($idLegacy, $idEfectorContext);
            if ($id !== null) {
                return static::findOne(['id' => $id, 'deleted_at' => null]);
            }
        }
        $id = static::findFirstPesIdByStaffOrPersona($idLegacy);
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
     * Filas PES (`id`), `id_persona`, `datos` para listados de médicos por efector.
     *
     * @return list<array{id:int, id_persona:int, datos:string}>
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

        return $rows;
    }

    /**
     * Todas las asignaciones PES activas en el efector (etiqueta apellido/nombre + servicio).
     *
     * @return list<array{id:int, datos:string}>
     */
    public static function listarOpcionesPorEfector(int $id_efector): array
    {
        if ($id_efector <= 0) {
            return [];
        }

        return static::find()
            ->alias('pes')
            ->select([
                'id' => 'pes.id',
                'datos' => 'CONCAT(COALESCE(personas.apellido,""), ", ", COALESCE(personas.nombre,""), " — ", servicios.nombre)',
            ])
            ->where(['pes.id_efector' => $id_efector, 'pes.deleted_at' => null])
            ->innerJoin('servicios', 'servicios.id_servicio = pes.id_servicio')
            ->join('LEFT JOIN', 'personas', 'pes.id_persona = personas.id_persona')
            ->orderBy(['personas.apellido' => SORT_ASC, 'personas.nombre' => SORT_ASC])
            ->asArray()
            ->all();
    }

    /**
     * @return list<array{id:int, id_persona:int, datos:string}>
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
