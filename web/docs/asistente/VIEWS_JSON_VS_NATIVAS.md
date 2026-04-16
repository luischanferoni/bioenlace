## Views JSON vs vistas nativas (Web / Flutter)

Este documento resume **cuándo usamos vistas basadas en JSON** (templates en `frontend/modules/api/v1/views/json/<entidad>/<accion>.json`) y **cuándo preferimos vistas nativas**:

- **Web**: vistas PHP/HTML de Yii2 (`views/.../*.php`, componentes JS/SPA).
- **Móvil**: pantallas nativas Flutter (widgets y layouts propios de la app).

No lista todos los lugares donde se usa cada enfoque; el objetivo es tener **criterios y ejemplos** para decidir.

---

## 1. Views JSON – Cuándo usarlas

Las views JSON representan **descriptores de UI** que se consumen desde la API. Pueden describir:

- wizards / formularios,
- listas,
- vistas de detalle,
- menús simples u otros layouts básicos.

En el backend se cargan con `UiDefinitionTemplateManager`:

```php
$config = \common\components\UiDefinitionTemplateManager::render('turnos', 'crear-como-paciente', $params);
```

Y se exponen por convención:

- `GET|POST /api/v1/views/<entidad>/<accion>` → `v1/<entidad>/<accion>` (descriptor + submit vía controller de entidad)

---

## 2. Vistas nativas – Cuándo usarlas

Preferir vistas nativas cuando:

- el layout/UX es rico (dashboards, múltiples cards, interacciones complejas),
- la UX debe ser muy específica por plataforma,
- el flujo es poco reutilizable,
- requiere interacción avanzada (drag&drop, gráficos, animaciones, etc.).

---

## 3. Cómo detectan Web y Flutter que deben construir una UI

La detección debe basarse en **metadatos del payload**, no en heurísticas de URL.

- En el contexto del asistente, `client_open.kind` indica explícitamente cómo abrir (`ui_json` o `native`).
- Para views JSON, el envoltorio típico es:

```json
{
  "kind": "ui_definition",
  "ui_type": "wizard",
  "wizard_config": {}
}
```

