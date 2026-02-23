<?php

namespace common\components;

use Yii;

/**
 * Carga y lista conversaciones desde archivos JSON en la carpeta de costos.
 * Convención: web/common/data/conversaciones/{tipo}/{archivo}.json
 * Tipos: pre_turno, pre_consulta, consulta_medico, onboarding, sistema.
 */
class ConversacionLoader
{
    /** @var string Ruta base relativa a @common/data */
    private const CONVERSACIONES_DIR = 'conversaciones';

    /**
     * Obtener la ruta absoluta de la carpeta de conversaciones.
     * @return string
     */
    public static function getCarpetaBase(): string
    {
        return Yii::getAlias('@common/data/' . self::CONVERSACIONES_DIR);
    }

    /**
     * Cargar una conversación por identificador "tipo/archivo" (sin .json).
     * @param string $conversacionId Ej. "pre_turno/sacar_turno_completo"
     * @return array|null ['tipo','nombre','descripcion','mensajes','userId'] o null si no existe/inválido
     */
    public static function cargar(string $conversacionId): ?array
    {
        $base = self::getCarpetaBase();
        $path = $base . '/' . str_replace(['..', '\\'], ['', '/'], $conversacionId) . '.json';
        if (!is_file($path)) {
            return null;
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }
        if (empty($data['mensajes']) || !is_array($data['mensajes'])) {
            return null;
        }
        $data['tipo'] = $data['tipo'] ?? '';
        $data['nombre'] = $data['nombre'] ?? basename($conversacionId);
        $data['descripcion'] = $data['descripcion'] ?? '';
        $data['userId'] = $data['userId'] ?? 'test-costos';
        return $data;
    }

    /**
     * Listar conversaciones disponibles (solo metadatos).
     * @return array[] [ ['id' => 'tipo/archivo', 'tipo' => ..., 'nombre' => ..., 'descripcion' => ...], ... ]
     */
    public static function listar(): array
    {
        $base = self::getCarpetaBase();
        if (!is_dir($base)) {
            return [];
        }
        $lista = [];
        $tipos = ['pre_turno', 'pre_consulta', 'consulta_medico', 'onboarding', 'sistema'];
        foreach ($tipos as $tipo) {
            $dir = $base . '/' . $tipo;
            if (!is_dir($dir)) {
                continue;
            }
            $archivos = glob($dir . '/*.json');
            foreach ($archivos as $archivo) {
                $nombreArchivo = basename($archivo, '.json');
                $id = $tipo . '/' . $nombreArchivo;
                $meta = self::leerMetadatos($archivo);
                $lista[] = [
                    'id' => $id,
                    'tipo' => $meta['tipo'] ?? $tipo,
                    'nombre' => $meta['nombre'] ?? $nombreArchivo,
                    'descripcion' => $meta['descripcion'] ?? '',
                ];
            }
        }
        return $lista;
    }

    /**
     * Leer solo tipo, nombre y descripcion de un archivo JSON sin cargar mensajes.
     * @param string $path Ruta al .json
     * @return array
     */
    private static function leerMetadatos(string $path): array
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        return [
            'tipo' => $data['tipo'] ?? '',
            'nombre' => $data['nombre'] ?? '',
            'descripcion' => $data['descripcion'] ?? '',
        ];
    }
}
