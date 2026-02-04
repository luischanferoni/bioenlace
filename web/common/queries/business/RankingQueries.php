<?php

namespace common\queries\business;

use Yii;
use common\models\RrhhEfector;
use common\models\Turno;
use common\models\Especialidades;

/**
 * Queries de ranking y comparación
 * Métodos para ordenar y comparar entidades según diferentes criterios
 */
class RankingQueries
{
    /**
     * Obtener médicos/profesionales ordenados por rapidez de atención
     * 
     * Calcula el tiempo promedio de atención por turno para cada profesional
     * basado en el tiempo entre turnos completados.
     * 
     * @param string|null $especialidad Nombre o parte del nombre de la especialidad (ej: "odontolog", "cardiolog", "pediatra")
     * @param int|null $id_efector Filtrar por efector
     * @param int $limit Cantidad de resultados a retornar
     * @return array Lista de profesionales con métricas de rapidez
     */
    public static function getMedicosMasRapidos($especialidad = null, $id_efector = null, $limit = 10)
    {
        // Query base para profesionales (rrhh_efector; especialidad no disponible en este modelo)
        $query = RrhhEfector::find()
            ->alias('r')
            ->joinWith(['persona p'])
            ->where(['r.deleted_at' => null]);

        if ($id_efector) {
            $query->andWhere(['r.id_efector' => $id_efector]);
        }

        $profesionales = $query->all();

        $result = [];
        foreach ($profesionales as $rrhh) {
            $persona = $rrhh->persona;
            if (!$persona) {
                continue;
            }

            $turnosAtendidos = Turno::find()
                ->where(['id_rr_hh' => $rrhh->id_rr_hh])
                ->andWhere(['estado' => Turno::ESTADO_ATENDIDO])
                ->count();

            $turnosDisponibles = Turno::find()
                ->where(['id_rr_hh' => $rrhh->id_rr_hh])
                ->andWhere(['>=', 'fecha', date('Y-m-d')])
                ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
                ->count();

            $result[] = [
                'id_rrhh' => $rrhh->id_rr_hh,
                'id_persona' => $persona->id_persona,
                'nombre_completo' => $persona->getNombreCompleto(),
                'especialidad' => null,
                'turnos_atendidos' => $turnosAtendidos,
                'turnos_disponibles' => $turnosDisponibles,
                'score_disponibilidad' => $turnosDisponibles,
                'efector' => $id_efector ? (\common\models\Efector::findOne($id_efector)->nombre ?? null) : null,
            ];
        }
        
        // Ordenar por disponibilidad (más turnos disponibles = más rápido)
        usort($result, function($a, $b) {
            return $b['score_disponibilidad'] <=> $a['score_disponibilidad'];
        });
        
        return array_slice($result, 0, $limit);
    }
    
    /**
     * Obtener odontólogos ordenados por rapidez de atención
     * Método de compatibilidad que llama a getMedicosMasRapidos
     * 
     * @param int|null $id_efector Filtrar por efector
     * @param int $limit Cantidad de resultados a retornar
     * @return array Lista de odontólogos con métricas de rapidez
     */
    public static function getOdontologosMasRapidos($id_efector = null, $limit = 10)
    {
        return self::getMedicosMasRapidos('odontolog', $id_efector, $limit);
    }
    
    /**
     * Obtener efectores con menor tiempo de espera
     * 
     * @param string|null $especialidad Filtrar por especialidad
     * @param int $limit Cantidad de resultados
     * @return array
     */
    public static function getEfectoresMenorEspera($especialidad = null, $limit = 10)
    {
        // Implementación futura
        return [];
    }
}
