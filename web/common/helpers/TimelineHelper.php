<?php

namespace common\helpers;

use DateTime;

/**
 * Utilidades para formatear fechas y tiempos relativos en el timeline.
 */
class TimelineHelper
{
    /**
     * Formatea una fecha para mostrar en el timeline.
     *
     * @param string $fecha Fecha en formato string
     * @return array Array con 'day', 'month' y 'year'
     */
    public static function formatTimelineDate($fecha)
    {
        $fechaObj = new DateTime($fecha);
        return [
            'day' => $fechaObj->format('d'),
            'month' => $fechaObj->format('M'),
            'year' => $fechaObj->format('Y')
        ];
    }

    /**
     * Calcula el tiempo relativo desde una fecha hasta ahora.
     *
     * @param string $fecha Fecha en formato string
     * @return string Tiempo relativo en español (ej: "Hace 2 días", "Ayer", etc.)
     */
    public static function getRelativeTime($fecha)
    {
        $fechaObj = new DateTime($fecha);
        $ahora = new DateTime();
        $diferencia = $ahora->diff($fechaObj);
        
        // Si es el mismo día
        if ($diferencia->days == 0) {
            if ($diferencia->h == 0) {
                if ($diferencia->i < 5) {
                    return 'Hace un momento';
                } else {
                    return 'Hace ' . $diferencia->i . ' min';
                }
            } else {
                return 'Hace ' . $diferencia->h . ' h';
            }
        }
        
        // Si es ayer
        if ($diferencia->days == 1) {
            return 'Ayer';
        }
        
        // Si es hace pocos días
        if ($diferencia->days < 7) {
            return 'Hace ' . $diferencia->days . ' días';
        }
        
        // Si es hace una semana
        if ($diferencia->days == 7) {
            return 'Hace 1 semana';
        }
        
        // Si es hace pocas semanas
        if ($diferencia->days < 30) {
            $semanas = floor($diferencia->days / 7);
            return 'Hace ' . $semanas . ' semana' . ($semanas > 1 ? 's' : '');
        }
        
        // Si es hace un mes
        if ($diferencia->days < 60) {
            return 'Hace 1 mes';
        }
        
        // Si es hace pocos meses
        if ($diferencia->days < 365) {
            $meses = floor($diferencia->days / 30);
            return 'Hace ' . $meses . ' mes' . ($meses > 1 ? 'es' : '');
        }
        
        // Si es hace un año
        if ($diferencia->days < 730) {
            return 'Hace 1 año';
        }
        
        // Si es hace varios años
        $años = floor($diferencia->days / 365);
        return 'Hace ' . $años . ' año' . ($años > 1 ? 's' : '');
    }

    /**
     * Formatea una fecha de forma amigable para mostrar en interfaces.
     * Retorna "Hoy", "Ayer", "Mañana" o formato "Jue 25/11"
     *
     * @param string $fecha Fecha en formato 'Y-m-d'
     * @return string Fecha formateada de forma amigable
     */
    public static function formatearFechaAmigable($fecha)
    {
        $hoy = date('Y-m-d');
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $manana = date('Y-m-d', strtotime('+1 day'));
        
        if ($fecha == $hoy) {
            return 'Hoy';
        } elseif ($fecha == $ayer) {
            return 'Ayer';
        } elseif ($fecha == $manana) {
            return 'Mañana';
        } else {
            // Formato: "Jue 25/11"
            $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            $diaSemana = $diasSemana[date('w', strtotime($fecha))];
            $dia = date('d', strtotime($fecha));
            $mes = date('m', strtotime($fecha));
            return $diaSemana . ' ' . $dia . '/' . $mes;
        }
    }
}

