<?php

namespace frontend\modules\api\v1;

use yii\helpers\Inflector;

/**
 * Mapa `id público → clase` de los controllers agrupados por dominio.
 *
 * El árbol es la fuente: `controllers/<dominio>/<X>Controller.php`. El id público **no**
 * lleva el dominio, así que reubicar un controller no cambia la URL, el route interno
 * (`v1/<id>/<accion>`) ni la ruta RBAC derivada del `uniqueId`.
 *
 * Los alias explícitos de `controllerMap` en la config del módulo tienen prioridad.
 */
final class DomainControllerMap
{
    private const SUFFIX = 'Controller';

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, string> id público → FQCN
     */
    public static function build(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $files = glob(__DIR__ . '/controllers/*/*' . self::SUFFIX . '.php') ?: [];
        sort($files);

        $out = [];
        foreach ($files as $file) {
            $class = basename($file, '.php');
            $id = Inflector::camel2id(substr($class, 0, -strlen(self::SUFFIX)), '-', true);
            if ($id === '' || isset($out[$id])) {
                continue;
            }
            $domain = basename(dirname($file));
            $out[$id] = __NAMESPACE__ . '\\controllers\\' . $domain . '\\' . $class;
        }

        self::$cache = $out;

        return $out;
    }
}
