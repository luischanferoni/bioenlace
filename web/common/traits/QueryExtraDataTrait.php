<?php

namespace common\traits;

/**
 * Permite acceder a resultados extra de una query,
 * sin agregarlos como attributos de la clase.
 *
 * @author aautalan
 */
trait QueryExtraDataTrait
{
    protected $_query_extra_data = [];

    public static function populateRecord($record, $row)
    {
        parent::populateRecord($record, $row);
        $columns = static::getTableSchema()->columns;
        foreach ($row as $name => $value) {
            if (!isset($columns[$name])) {
                $record->_query_extra_data[$name] = $value;
            }
        }
    }

    /*
     * Si se llama sin parametros, retorna array completo de
     * resultados extra.
     * Si se llama con $name, retorna el valor del resultado
     * con clave $name. Si no existe, retorna null.
     */
    public function getQueryExtraData($name = null)
    {
        if ($name == null) {
            return $this->_query_extra_data;
        } elseif (isset($this->_query_extra_data[$name])) {
            return $this->_query_extra_data[$name];
        }
        return null;
    }
}
