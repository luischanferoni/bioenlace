# Business Queries

Sistema para gestionar consultas de negocio complejas (rankings, métricas, agregaciones).

## Estructura

- `business/` - Clases PHP con la lógica de las queries
- `metadata/business_queries.json` - Metadatos y keywords para asociar consultas con métodos

## Agregar una nueva Business Query

### 1. Crear el método en una clase de business queries

```php
// En common/queries/business/RankingQueries.php

/**
 * Obtener efectores con menor tiempo de espera
 * 
 * @param string|null $especialidad Filtrar por especialidad
 * @param int $limit Cantidad de resultados
 * @return array
 */
public static function getEfectoresMenorEspera($especialidad = null, $limit = 10)
{
    // Lógica de negocio aquí
    return [];
}
```

### 2. Registrar en business_queries.json

```json
{
  "id": "efectores_menor_espera",
  "class": "common\\queries\\business\\RankingQueries",
  "method": "getEfectoresMenorEspera",
  "keywords": ["efector", "espera", "tiempo", "rapido", "menor"],
  "entity_type": "Efectores",
  "query_type": "ranking",
  "description": "Efectores con menor tiempo de espera promedio",
  "parameters": [
    {
      "name": "especialidad",
      "type": "string",
      "required": false,
      "description": "Filtrar por especialidad"
    },
    {
      "name": "limit",
      "type": "integer",
      "required": false,
      "default": 10
    }
  ],
  "active": true
}
```

## Keywords

Los keywords son palabras que la IA usará para asociar la consulta del usuario con esta query. Incluye:
- Sinónimos
- Variaciones (con/sin acentos)
- Términos relacionados
