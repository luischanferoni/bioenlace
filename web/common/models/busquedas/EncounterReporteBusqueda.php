<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use common\models\ProfesionalEfectorServicio;
use yii\base\Model;

/**
 * Planillas / reportes ministeriales sobre {@see Encounter} (sin tabla `consultas`).
 */
class EncounterReporteBusqueda extends Model
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchParaReporteC4($id_efector, $idServicio, $idMedico, $desde, $hasta, $tipoAtencion): array
    {
        $filtroProfesional = static::reporteProfesionalFilterParaEncounter($id_efector, $idServicio, $idMedico);
        $encTable = Encounter::tableName();

        if ($tipoAtencion === 'AMB') {
            $query1 = (new \yii\db\Query())
                ->select([
                    'id_consulta' => 'enc.id',
                    'encounter_id' => 'enc.id',
                    'personas.id_persona',
                    'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                    'personas.documento',
                    'personas.fecha_nacimiento',
                    'personas.sexo_biologico',
                    'turnos.fecha',
                    'turnos.hora',
                ])
                ->from(['enc' => $encTable])
                ->leftJoin(
                    'turnos',
                    '(turnos.id_turnos = enc.appointment_id) OR (enc.parent_type = :turnoType AND turnos.id_turnos = enc.parent_id)',
                    [':turnoType' => Encounter::PARENT_TURNO]
                )
                ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                ->andWhere(['enc.efector_id' => (int) $id_efector])
                ->andWhere(['>=', 'turnos.fecha', $desde])
                ->andWhere(['<=', 'turnos.fecha', $hasta])
                ->andWhere($filtroProfesional)
                ->andWhere(['enc.service_id' => (int) $idServicio])
                ->andWhere(['enc.deleted_at' => null]);

            $query2 = (new \yii\db\Query())
                ->select([
                    'id_consulta' => 'enc.id',
                    'encounter_id' => 'enc.id',
                    'personas.id_persona',
                    'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                    'personas.documento',
                    'personas.fecha_nacimiento',
                    'personas.sexo_biologico',
                    'fecha' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%Y-%m-%d")'),
                    'hora' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%H:%i:%s")'),
                ])
                ->from(['enc' => $encTable])
                ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                ->andWhere(['enc.efector_id' => (int) $id_efector])
                ->andWhere(['>=', new \yii\db\Expression('DATE(enc.created_at)'), $desde])
                ->andWhere(['<=', new \yii\db\Expression('DATE(enc.created_at)'), $hasta])
                ->andWhere($filtroProfesional)
                ->andWhere(['enc.service_id' => (int) $idServicio])
                ->andWhere(['enc.parent_type' => Encounter::PARENT_GENERICO_AMB])
                ->andWhere(['enc.deleted_at' => null]);

            return (new \yii\db\Query())
                ->from(['resultados' => $query1->union($query2)])
                ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
                ->all();
        }

        $query1 = (new \yii\db\Query())
            ->select([
                'id_consulta' => 'enc.id',
                'encounter_id' => 'enc.id',
                'personas.id_persona',
                'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                'personas.documento',
                'personas.fecha_nacimiento',
                'personas.sexo_biologico',
                'guardia.fecha',
                'guardia.hora',
            ])
            ->from(['enc' => $encTable])
            ->leftJoin('guardia', 'guardia.id = enc.parent_id AND enc.parent_type = :guardiaType', [':guardiaType' => Encounter::PARENT_GUARDIA])
            ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
            ->andWhere(['enc.efector_id' => (int) $id_efector])
            ->andWhere(['>=', 'guardia.fecha', $desde])
            ->andWhere(['<=', 'guardia.fecha', $hasta])
            ->andWhere($filtroProfesional)
            ->andWhere(['enc.service_id' => (int) $idServicio])
            ->andWhere(['enc.deleted_at' => null]);

        $query2 = (new \yii\db\Query())
            ->select([
                'id_consulta' => 'enc.id',
                'encounter_id' => 'enc.id',
                'personas.id_persona',
                'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                'personas.documento',
                'personas.fecha_nacimiento',
                'personas.sexo_biologico',
                'fecha' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%Y-%m-%d")'),
                'hora' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%H:%i:%s")'),
            ])
            ->from(['enc' => $encTable])
            ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
            ->andWhere(['enc.efector_id' => (int) $id_efector])
            ->andWhere(['>=', new \yii\db\Expression('DATE(enc.created_at)'), $desde])
            ->andWhere(['<=', new \yii\db\Expression('DATE(enc.created_at)'), $hasta])
            ->andWhere($filtroProfesional)
            ->andWhere(['enc.service_id' => (int) $idServicio])
            ->andWhere(['enc.parent_type' => Encounter::PARENT_GENERICO_EMER])
            ->andWhere(['enc.deleted_at' => null]);

        return (new \yii\db\Query())
            ->from(['resultados' => $query1->union($query2)])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchParaReporte5($id_efector, $idServicio, $fecha): array
    {
        return (new \yii\db\Query())
            ->select([
                'fecha' => new \yii\db\Expression('DATE(enc.created_at)'),
                'dia' => new \yii\db\Expression('DAY(enc.created_at)'),
                'menor1anioM' => new \yii\db\Expression('sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) < 1 ,1, 0) )'),
                'menor1anioF' => new \yii\db\Expression('sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) < 1 ,1, 0) )'),
                '1anioM' => new \yii\db\Expression('sum(IF(p.sexo = "M" and (TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) >= 1 and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) < 2) ,1, 0) )'),
                '1anioF' => new \yii\db\Expression('sum(IF(p.sexo = "F" and (TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) >= 1 and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) < 2) ,1, 0) )'),
                '2a4M' => new \yii\db\Expression('sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 2 and  4 ,1, 0) )'),
                '2a4F' => new \yii\db\Expression('sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 2 and  4 ,1, 0) )'),
                '5a9M' => new \yii\db\Expression('sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 5 and  9 ,1, 0) )'),
                '5a9F' => new \yii\db\Expression('sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 5 and  9 ,1, 0) )'),
                '10a14M' => new \yii\db\Expression('sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 10 and  14 ,1, 0) )'),
                '10a14F' => new \yii\db\Expression('sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 10 and  14 ,1, 0) )'),
                '15a49M' => new \yii\db\Expression('sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 15 and  49 ,1, 0) )'),
                '15a49F' => new \yii\db\Expression('sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) BETWEEN 15 and  49 ,1, 0) )'),
                'mayor50M' => new \yii\db\Expression('sum(IF(p.sexo = "M" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) >= 50 ,1, 0) )'),
                'matyor50F' => new \yii\db\Expression('sum(IF(p.sexo = "F" and TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,enc.created_at) >= 50 ,1, 0) )'),
            ])
            ->from(['enc' => Encounter::tableName()])
            ->leftJoin('personas p', 'enc.subject_persona_id = p.id_persona')
            ->andWhere(['enc.efector_id' => (int) $id_efector])
            ->andWhere(['enc.service_id' => (int) $idServicio])
            ->andWhere(['enc.deleted_at' => null])
            ->andWhere(['YEAR(enc.created_at)' => date('Y', strtotime($fecha))])
            ->andWhere(['MONTH(enc.created_at)' => date('m', strtotime($fecha))])
            ->groupBy([new \yii\db\Expression('DATE(enc.created_at)')])
            ->orderBy(['enc.created_at' => SORT_ASC])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchReporteFarmacia($id_efector, $idServicio, $fecha, $tipoAtencion): array
    {
        $encTable = Encounter::tableName();

        if ($tipoAtencion === 'AMB') {
            $query1 = (new \yii\db\Query())
                ->select([
                    'id_consulta' => 'enc.id',
                    'encounter_id' => 'enc.id',
                    'personas.id_persona',
                    'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                    'personas.documento',
                    'personas.fecha_nacimiento',
                    'personas.sexo_biologico',
                    'turnos.fecha',
                    'turnos.hora',
                ])
                ->from(['enc' => $encTable])
                ->leftJoin(
                    'turnos',
                    '(turnos.id_turnos = enc.appointment_id) OR (enc.parent_type = :turnoType AND turnos.id_turnos = enc.parent_id)',
                    [':turnoType' => Encounter::PARENT_TURNO]
                )
                ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                ->andWhere(['enc.efector_id' => (int) $id_efector])
                ->andWhere(['turnos.fecha' => $fecha])
                ->andWhere(['enc.service_id' => (int) $idServicio])
                ->andWhere(['enc.deleted_at' => null]);

            $query2 = (new \yii\db\Query())
                ->select([
                    'id_consulta' => 'enc.id',
                    'encounter_id' => 'enc.id',
                    'personas.id_persona',
                    'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                    'personas.documento',
                    'personas.fecha_nacimiento',
                    'personas.sexo_biologico',
                    'fecha' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%Y-%m-%d")'),
                    'hora' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%H:%i:%s")'),
                ])
                ->from(['enc' => $encTable])
                ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                ->andWhere(['enc.efector_id' => (int) $id_efector])
                ->andWhere(['DATE(enc.created_at)' => $fecha])
                ->andWhere(['enc.service_id' => (int) $idServicio])
                ->andWhere(['enc.parent_type' => Encounter::PARENT_GENERICO_AMB])
                ->andWhere(['enc.deleted_at' => null]);

            return (new \yii\db\Query())
                ->from(['resultados' => $query1->union($query2)])
                ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
                ->all();
        }

        if ($tipoAtencion === 'EMER') {
            $query1 = (new \yii\db\Query())
                ->select([
                    'id_consulta' => 'enc.id',
                    'encounter_id' => 'enc.id',
                    'personas.id_persona',
                    'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                    'personas.documento',
                    'personas.fecha_nacimiento',
                    'personas.sexo_biologico',
                    'guardia.fecha',
                    'guardia.hora',
                ])
                ->from(['enc' => $encTable])
                ->leftJoin('guardia', 'guardia.id = enc.parent_id AND enc.parent_type = :guardiaType', [':guardiaType' => Encounter::PARENT_GUARDIA])
                ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                ->andWhere(['enc.efector_id' => (int) $id_efector])
                ->andWhere(['guardia.fecha' => $fecha])
                ->andWhere(['enc.service_id' => (int) $idServicio])
                ->andWhere(['enc.deleted_at' => null]);

            $query2 = (new \yii\db\Query())
                ->select([
                    'id_consulta' => 'enc.id',
                    'encounter_id' => 'enc.id',
                    'personas.id_persona',
                    'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                    'personas.documento',
                    'personas.fecha_nacimiento',
                    'personas.sexo_biologico',
                    'fecha' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%Y-%m-%d")'),
                    'hora' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%H:%i:%s")'),
                ])
                ->from(['enc' => $encTable])
                ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
                ->andWhere(['enc.efector_id' => (int) $id_efector])
                ->andWhere(['DATE(enc.created_at)' => $fecha])
                ->andWhere(['enc.service_id' => (int) $idServicio])
                ->andWhere(['enc.parent_type' => Encounter::PARENT_GENERICO_EMER])
                ->andWhere(['enc.deleted_at' => null]);

            return (new \yii\db\Query())
                ->from(['resultados' => $query1->union($query2)])
                ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
                ->all();
        }

        return (new \yii\db\Query())
            ->select([
                'id_consulta' => 'enc.id',
                'encounter_id' => 'enc.id',
                'personas.id_persona',
                'nombreyapellido' => new \yii\db\Expression('CONCAT(personas.apellido," ",personas.nombre)'),
                'personas.documento',
                'personas.fecha_nacimiento',
                'personas.sexo_biologico',
                'fecha' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%Y-%m-%d")'),
                'hora' => new \yii\db\Expression('DATE_FORMAT(enc.created_at, "%H:%i:%s")'),
            ])
            ->from(['enc' => $encTable])
            ->leftJoin('personas', 'enc.subject_persona_id = personas.id_persona')
            ->andWhere(['enc.efector_id' => (int) $id_efector])
            ->andWhere(['DATE(enc.created_at)' => $fecha])
            ->andWhere(['enc.service_id' => (int) $idServicio])
            ->andWhere(['enc.parent_type' => Encounter::PARENT_INTERNACION])
            ->andWhere(['enc.deleted_at' => null])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchReporteOdontologia($id_efector, $idServicio, $fecha): array
    {
        $procTable = \common\models\Clinical\Procedure::tableName();

        return (new \yii\db\Query())
            ->select([
                'codigo' => 'p.code',
                'cantidad' => new \yii\db\Expression('count(DISTINCT enc.id)'),
            ])
            ->from(['enc' => Encounter::tableName()])
            ->innerJoin(
                ['p' => $procTable],
                'p.encounter_id = enc.id AND p.deleted_at IS NULL'
            )
            ->innerJoin(
                ['e' => \common\models\Clinical\ProcedureOdontologyExt::tableName()],
                'e.procedure_id = p.id'
            )
            ->andWhere(['enc.efector_id' => (int) $id_efector])
            ->andWhere(['DATE_FORMAT(enc.created_at, "%Y-%m")' => $fecha])
            ->andWhere(['enc.service_id' => (int) $idServicio])
            ->andWhere(['enc.deleted_at' => null])
            ->andWhere(['IS NOT', 'p.code', null])
            ->andWhere(['!=', 'p.code', ''])
            ->groupBy(['p.code'])
            ->orderBy(['p.code' => SORT_ASC])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function reporteProfesionalFilterParaEncounter($id_efector, $idServicio, $idMedico): array
    {
        $idEfector = (int) $id_efector;
        $idServ = (int) $idServicio;
        $idStaff = (int) $idMedico;

        if ($idStaff <= 0) {
            return ['enc.id_profesional_efector_servicio' => null];
        }

        $idPersona = (int) (ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId($idStaff) ?? 0);
        $pesIds = [];
        if ($idPersona > 0 && $idEfector > 0 && $idServ > 0) {
            $pesIds = ProfesionalEfectorServicio::find()
                ->select(['id'])
                ->where([
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'id_servicio' => $idServ,
                    'deleted_at' => null,
                ])
                ->column();
        }
        if ($pesIds === []) {
            return ['enc.id_profesional_efector_servicio' => -1];
        }

        return ['enc.id_profesional_efector_servicio' => $pesIds];
    }
}
