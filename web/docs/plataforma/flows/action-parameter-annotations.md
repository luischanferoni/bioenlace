# Anotaciones de parámetros para formularios dinámicos

## Objetivo

Documentar parámetros de acciones API para que el analizador genere formularios dinámicos cuando faltan datos.

## Actores

- Desarrollador que documenta `action*` en controladores API.
- Cliente asistente / UI que consume `action_analysis`.

## Anclas

| Área | Analizador de docblocks en capa API / Intent |
|------|-----------------------------------------------|

---

## @paramOption

Define tipo de control y fuente de opciones para un parámetro.

| Parte | Valores |
|-------|---------|
| Sintaxis | `@paramOption nombre_param tipo fuente` o `fuente\|filtro` |
| Tipos de control | `select`, `autocomplete`, `date`, `number`, `text` |
| Fuentes | `efectores`, `servicios`, `personas`, `rrhh`, `especialidades` |
| Filtros | `user_efectores`, `efector_servicios` |

Ejemplo declarativo (turno): `id_efector` → select efectores filtrados al usuario; `id_servicio` → select servicios del efector; `id_persona` → autocomplete personas. Los `@param` estándar de PHPDoc describen tipo y texto de ayuda en la misma acción `actionCreate`.

---

## @paramFilter

Filtro adicional sobre las opciones de un parámetro.

| Parte | Valores |
|-------|---------|
| Sintaxis | `@paramFilter nombre_param tipo_filtro valor` |
| Ejemplo | `id_servicio` + `servicio_especialidad` + `odontologia` |

---

## @paramDepends

Indica que las opciones de un parámetro dependen de otro ya elegido.

| Parte | Valores |
|-------|---------|
| Sintaxis | `@paramDepends nombre_param parametro_padre` |
| Ejemplo | `id_servicio` depende de `id_efector` |

---

## @paramEndpoint

Ruta custom para cargar opciones (autocomplete u otros).

| Parte | Valores |
|-------|---------|
| Sintaxis | `@paramEndpoint nombre_param /ruta/relativa` |
| Ejemplo | `id_persona` → `/api/v1/personas/search` |

---

## Combinación típica (crear turno)

| Parámetro | Anotaciones |
|-----------|-------------|
| `id_efector` | `@paramOption` select + `user_efectores` |
| `id_servicio` | `@paramOption` select + `efector_servicios`, `@paramDepends` desde `id_efector`, `@paramFilter` opcional por especialidad |
| `id_persona` | `@paramOption` autocomplete + `@paramEndpoint` búsqueda |
| `fecha` | `@paramOption` date |

---

## Respuesta cuando faltan parámetros

El cuerpo incluye un objeto `action_analysis` con:

| Campo | Contenido |
|-------|-----------|
| `ready_to_execute` | `false` hasta completar requeridos |
| `parameters.provided` | Parámetros ya inferidos o enviados (`value`, `source`) |
| `parameters.missing` | Lista con `name`, `type`, `required`, `description` |
| `options` | Por parámetro: `type` (select, etc.) y `endpoint` si aplica |
| `form_config.fields` | Definición para render (name, label, type, required, endpoint) |

Ejemplo conceptual: si solo llegó `id_persona`, `missing` incluye `id_efector` e `id_servicio` y `options.id_efector` apunta al endpoint de búsqueda de efectores.

---

## Relacionado

- [design.md](../design.md)
- [asistente/flows/UI_JSON_DESCRIPTOR_CONTRACT.md](../../asistente/flows/UI_JSON_DESCRIPTOR_CONTRACT.md)
