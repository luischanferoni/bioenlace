## Vistas dinámicas JSON vs vistas nativas (Web / Flutter)

Este documento resume **cuándo usamos vistas dinámicas basadas en JSON** (por ejemplo `frontend/modules/api/v1/views/json/turnos/crear-como-paciente.json`) y **cuándo preferimos vistas nativas**:

- **Web**: vistas PHP/HTML de Yii2 (`views/.../*.php`, componentes JS/SPA).
- **Móvil**: pantallas nativas Flutter (widgets y layouts propios de la app).

No lista todos los lugares donde se usa cada enfoque; el objetivo es tener **criterios y ejemplos** para decidir.

---

## 1. Vistas dinámicas JSON – Cuándo usarlas

Las vistas JSON representan **descriptores de UI** que se consumen desde la API. Pueden describir:

- wizards / formularios,
- listas,
- vistas de detalle,
- menús simples u otros layouts básicos.

En el backend se cargan con **`UiDefinitionTemplateManager`** (antes se usaba el nombre `FormConfigTemplateManager`, deprecado: no se trata solo de “formularios”, sino de **definiciones de UI**):

```php
$config = \common\components\UiDefinitionTemplateManager::render('turnos', 'crear-como-paciente', $params);
// devuelve ['wizard_config' => [...]]
```

La app (web o móvil) recibe normalmente un `wizard_config` (para wizards/forms) u otra estructura equivalente para listas/detalles, que describe:

- **Pasos del wizard** (`steps`), cuando corresponde: título, orden, qué campos aparecen en cada paso.
- **Campos** (`fields`): `name`, `label`, `type`, `required`, validaciones básicas.
- **Dependencias** (`depends_on`, `params`): qué campo depende de cuál.
- **Opciones dinámicas** (`option_config`, `{{options}}`): catálogos que trae el backend.

### 1.1. Casos donde el JSON tiene más sentido

Usar vistas dinámicas JSON cuando el flujo cumple la mayoría de estas condiciones:

- **A. Es un flujo de UI de negocio relativamente estándar**
  - Formularios o wizards multi‑paso, con campos relativamente estándar: texto, select, autocomplete, fechas, etc.
  - Listas de datos con filtros sencillos.
  - Vistas de detalle simples (título + pares label/valor + algunas acciones).
  - Ejemplo: *“Crear mi turno”* (`turnos/crear-como-paciente`), donde el JSON define:
    - paso 0: servicio + efector,
    - paso 1: profesional,
    - paso 2: fecha + hora,
    - paso 3: tipo de atención.

- **B. Debe ser compartido entre Web y App móvil**
  - Queremos que web y Flutter lean **la misma definición** de formulario desde la API.
  - Cambios en pasos/campos se hacen en backend (JSON) sin tocar Flutter.

- **C. La estructura cambia seguido por negocio**
  - Agregar/quitar campos o pasos es relativamente frecuente.
  - Interesa que el cambio se pueda hacer “solo con config” sin redeploy de apps.

- **D. Las opciones dependen de lógica de backend**
  - Listas que ya se resuelven del lado servidor: servicios por efector, efectores del usuario, etc.
  - El JSON sólo declara `option_config` y el backend trae los valores.

En resumen: **cuando es principalmente un formulario de negocio, reutilizable y con lógica de opciones en backend, el JSON es el enfoque preferido.**

---

## 2. Vistas nativas (HTML Yii / Flutter) – Cuándo usarlas

Las vistas nativas son:

- **Web**: vistas Yii2 (`views/.../*.php`), componentes JS, vistas SPA.
- **Móvil**: pantallas Flutter construidas con widgets y navegación propia.

### 2.1. Casos donde la vista nativa es mejor

Preferir vistas nativas cuando se da alguno de estos escenarios:

- **A. Pantallas de presentación ricas o dashboards**
  - Múltiples cards, iconos SVG, bloques de información, layout complejo.
  - Ejemplo: la vista de detalle de persona (`views/personas/view.php`) con:
    - cards de Historia Clínica, Más datos, Turnos, Internación, etc.,
    - muchas secciones con enlaces y modales,
    - integración con scripts específicos (`consultas.js`, navegación SPA, etc.).
  - Modelar todo eso en un JSON genérico lo hace muy rígido o muy complejo.

- **B. UX específica por plataforma**
  - En móvil se quiere usar navegación nativa (tabs, bottom sheets, gestos),
    e interfaces que aprovechan Flutter al máximo.
  - En web se puede querer un layout distinto (grid, tablas, tooltips, popovers).
  - Forzar ambas a una sola “plantilla JSON de UI” empobrece la experiencia.

- **C. Flujo muy específico, poco reutilizable**
  - Pantallas que difícilmente se usen igual entre web y móvil.
  - Lógica y componentes muy acoplados a la plataforma.

- **D. Requiere interacción avanzada**
  - Animaciones custom, validaciones complejas en cliente, componentes gráficos,
    drag & drop, timelines ricos, etc.
  - Más sencillo y mantenible en código nativo que en un DSL JSON.

En resumen: **cuando la UI es rica, específica de la plataforma o muy custom, seguimos usando vistas nativas.**

---

## 3. Regla práctica para decidir

Al diseñar un nuevo flujo, usar esta mini‑checklist:

- **¿Es principalmente un wizard de formulario de negocio?**
  - Sí → considerar JSON.
  - No → probablemente mejor vista nativa.

- **¿Debe verse (casi) igual en Web y App móvil, y compartir campos/pasos?**
  - Sí → fuerte candidato a JSON.
  - No, cada una tendrá UX distinta → mejor vistas nativas propias.

- **¿Los cambios que pide negocio son sobre todo “agregar/quitar campos/pasos”?**
  - Sí, y queremos cambiarlos sin tocar Flutter/web → JSON ayuda mucho.
  - No, los cambios son sobre layout/UX avanzada → vista nativa.

- **¿La lógica de opciones depende fuertemente del backend (catálogos, filtros, permisos)?**
  - Sí → JSON encaja bien con el modelo actual (`option_config`, `{{options}}`).
  - No, son pocas opciones o muy visuales → puede ir directo en la vista nativa.

Si al menos **3 de las 4 preguntas apuntan a “Sí → JSON”**, el flujo es un buen candidato para implementarse como **vista dinámica JSON** y exponerlo via `wizard_config`.  
En caso contrario, **seguir con vistas nativas** (Yii para web, Flutter para móvil) suele dar más flexibilidad y mejor experiencia de usuario.

---

## 4. Ejemplos orientativos (sin listar todo el sistema)

- **Buenos candidatos a JSON dinámico**
  - Flujos de creación/edición de entidades de negocio donde:
    - la secuencia de pasos es clara (seleccionar efector/servicio → profesional → fecha/hora),
    - las opciones dependen de relaciones del modelo (efectores del usuario, servicios del efector),
    - se quiere reutilizar el mismo flujo en chat, web y app móvil.

- **Buenos candidatos a vistas nativas**
  - Pantallas de inicio, dashboards, vistas de detalle ricas (`personas/view`, inicio de turnos, etc.).
  - Secciones que mezclan muchas tarjetas y enlaces a otros módulos.
  - Cualquier interfaz donde el valor principal está en la experiencia de uso y el diseño, más que en la “configurabilidad” del formulario.

---

## 5. Cómo detectan Web y Flutter que deben construir una UI

Para que los clientes (web y Flutter) sepan que una respuesta contiene una **definición de UI** y no solo datos, usamos dos niveles de convención:

- **Rutas dedicadas a UI dinámica** (por ejemplo, bajo `/api/v1/ui/...`).
- **Metadatos en el JSON** (`kind`, `ui_type`, etc.).

En el contexto del **asistente**, además se incluye `client_open` en las acciones sugeridas para indicar explícitamente que se debe abrir una pantalla UI JSON (ver `web/docs/CHAT_ACTIONS_CONTRACT.md`).

### 5.1. Convención de rutas

**Implementado en `frontend/config/main.php`:**

- `GET /api/v1/ui/<entidad>/<accion>` → `v1/<entidad>/<accion>` (descriptor UI JSON renderizado por el controller de la entidad)
- `OPTIONS /api/v1/ui/<entidad>/<accion>` → CORS / options del controller de la entidad

El permiso sigue el mismo `action_id` (`entidad.accion`).

Ejemplos de URLs:

- `GET /api/v1/ui/turnos/crear-como-paciente`
- `GET /api/v1/ui/personas/actualizar-datos` (cuando exista la acción descubierta)

Los clientes pueden asumir que:

- Si llaman a una ruta bajo `/ui/...`, la respuesta será un **descriptor de UI**.
- Si llaman a rutas “normales” (no `/ui/...`), la respuesta será data de negocio o resultados de acciones.

**Importante (arquitectura):** `/api/v1/ui/...` **solo** devuelve definiciones de UI en JSON desde plantillas (`views/json/...`) y **no** invoca controladores web del frontend (`frontend/controllers`). El render se hace en el controller API de cada entidad (por ejemplo `TurnosController::actionCrearComoPaciente` para `turnos/crear-como-paciente`).

### 5.2. Metadatos en el JSON

Además de `wizard_config`, las respuestas de UI pueden incluir un envoltorio estándar como:

```json
{
  "kind": "ui_definition",
  "ui_type": "wizard",
  "wizard_config": { },
  "compatibility": { }
}
```

### 5.3. Compatibilidad por cliente y versión de app

En el JSON del flujo se puede declarar opcionalmente **`ui_meta`** (queda dentro de `wizard_config` tras el merge):

```json
"ui_meta": {
  "schema_version": "1",
  "clients": {
    "*": { "min_app_version": "1.0.0" },
    "paciente-flutter": { "min_app_version": "2.0.0", "max_app_version": "99.0.0" }
  }
}
```

El backend usa los headers `X-App-Client` y `X-App-Version` y adjunta **`compatibility`** en la respuesta. Ver `UiDefinitionTemplateManager::evaluateClientCompatibility()`.

**Cliente web:** en `views/layouts/main.php` se define `window.spaConfig.appVersion` (parámetro `spaWebAppVersion` en `frontend/config/params.php`) y `window.getBioenlaceApiClientHeaders()`. La SPA (`spa-home.js`), `ajax-wrapper.js` (`VitaMindAjax.fetchPost`), listado de pacientes y envíos relevantes de `chat-inteligente.js` envían esos headers automáticamente.

**App móvil (Flutter u otra):** conviene enviar en todas las peticiones a `/api/v1/*` los mismos headers, por ejemplo `X-App-Client: paciente-flutter` y `X-App-Version` con el semver de la app (desde `package_info` o equivalente).
