<?php

namespace common\components\Infra\Migration;

/**
 * Definición de columnas ENUM MySQL con literales en MAYÚSCULAS (convención Bioenlace).
 *
 * En el AR del dominio: constantes homónimas (ej. {@see \common\models\Servicio::TELECONSULTA_POLITICA_NINGUNA}).
 */
final class MigrationEnumColumn
{
    /**
     * @param list<string> $values Literales del ENUM, todos en MAYÚSCULAS
     * @param string|null $comment Comentario SQL opcional (p. ej. lista de valores)
     */
    public static function mysqlEnum(array $values, string $default, bool $notNull = true, ?string $comment = null): string
    {
        self::assertValues($values, $default);

        $quoted = array_map(static function (string $v): string {
            return "'" . str_replace("'", "''", $v) . "'";
        }, $values);

        $sql = 'ENUM(' . implode(',', $quoted) . ')';
        if ($notNull) {
            $sql .= ' NOT NULL';
        }
        $sql .= " DEFAULT '" . str_replace("'", "''", $default) . "'";
        if ($comment !== null && $comment !== '') {
            $sql .= " COMMENT '" . str_replace("'", "''", $comment) . "'";
        }

        return $sql;
    }

    /**
     * @param list<string> $values
     */
    public static function assertValues(array $values, string $default): void
    {
        if ($values === []) {
            throw new \InvalidArgumentException('MigrationEnumColumn: values no puede estar vacío.');
        }
        foreach ($values as $v) {
            if ($v === '' || $v !== strtoupper($v)) {
                throw new \InvalidArgumentException(
                    'MigrationEnumColumn: cada valor debe ser MAYÚSCULAS no vacío; recibido: ' . var_export($v, true)
                );
            }
        }
        if (!in_array($default, $values, true)) {
            throw new \InvalidArgumentException('MigrationEnumColumn: default debe estar en values.');
        }
    }
}
