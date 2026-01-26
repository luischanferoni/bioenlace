# Anotaciones de Parámetros para Formularios Dinámicos

Este documento explica cómo documentar parámetros de acciones para que el sistema pueda generar formularios dinámicos automáticamente.

## Anotaciones Disponibles

### @paramOption

Define el tipo de opciones para un parámetro.

**Sintaxis:**
```
@paramOption nombre_param tipo source|filter
```

**Ejemplo:**
```php
/**
 * Crear un nuevo turno
 * 
 * @paramOption id_efector select efectores|user_efectores
 * @paramOption id_servicio select servicios|efector_servicios
 * @paramOption id_persona autocomplete personas
 * 
 * @param integer $id_efector Efector donde se realizará el turno
 * @param integer $id_servicio Servicio médico requerido
 * @param integer $id_persona Persona que solicita el turno
 */
public function actionCreate($id_efector, $id_servicio, $id_persona)
```

**Tipos disponibles:**
- `select` - Lista desplegable
- `autocomplete` - Búsqueda con autocompletado
- `date` - Selector de fecha
- `number` - Campo numérico
- `text` - Campo de texto

**Fuentes disponibles:**
- `efectores` - Lista de efectores
- `servicios` - Lista de servicios
- `personas` - Búsqueda de personas
- `rrhh` - Búsqueda de profesionales
- `especialidades` - Lista de especialidades

**Filtros:**
- `user_efectores` - Solo efectores del usuario
- `efector_servicios` - Servicios del efector seleccionado

### @paramFilter

Aplica filtros adicionales a las opciones.

**Sintaxis:**
```
@paramFilter nombre_param tipo_filtro valor
```

**Ejemplo:**
```php
/**
 * @paramOption id_servicio select servicios|efector_servicios
 * @paramFilter id_servicio servicio_especialidad odontologia
 */
```

### @paramDepends

Indica que un parámetro depende de otro.

**Sintaxis:**
```
@paramDepends nombre_param depende_de
```

**Ejemplo:**
```php
/**
 * @paramOption id_efector select efectores|user_efectores
 * @paramOption id_servicio select servicios|efector_servicios
 * @paramDepends id_servicio id_efector
 */
```

### @paramEndpoint

Especifica un endpoint personalizado para obtener opciones.

**Sintaxis:**
```
@paramEndpoint nombre_param /ruta/endpoint
```

**Ejemplo:**
```php
/**
 * @paramOption id_persona autocomplete personas
 * @paramEndpoint id_persona /api/v1/personas/search
 */
```

## Ejemplo Completo

```php
/**
 * Crear un nuevo turno
 * 
 * @paramOption id_efector select efectores|user_efectores
 * @paramOption id_servicio select servicios|efector_servicios
 * @paramFilter id_servicio servicio_especialidad odontologia
 * @paramDepends id_servicio id_efector
 * @paramOption id_persona autocomplete personas
 * @paramEndpoint id_persona /api/v1/personas/search
 * @paramOption fecha date
 * 
 * @param integer $id_efector Efector donde se realizará el turno
 * @param integer $id_servicio Servicio médico requerido
 * @param integer $id_persona Persona que solicita el turno
 * @param string $fecha Fecha del turno (YYYY-MM-DD)
 */
public function actionCreate($id_efector, $id_servicio, $id_persona, $fecha = null)
{
    // ...
}
```

## Respuesta JSON Generada

Cuando faltan parámetros, el sistema devuelve:

```json
{
  "action_analysis": {
    "ready_to_execute": false,
    "parameters": {
      "provided": {
        "id_persona": {
          "value": "12345",
          "source": "extracted"
        }
      },
      "missing": [
        {
          "name": "id_efector",
          "type": "integer",
          "required": true,
          "description": "Efector donde se realizará el turno"
        }
      ]
    },
    "options": {
      "id_efector": {
        "type": "select",
        "endpoint": "/efectores/search"
      }
    },
    "form_config": {
      "fields": [
        {
          "name": "id_efector",
          "label": "Id Efector",
          "type": "select",
          "required": true,
          "endpoint": "/efectores/search"
        }
      ]
    }
  }
}
```
