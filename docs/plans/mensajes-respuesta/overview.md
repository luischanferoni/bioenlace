# Overview — qué encontramos sobre mensajes

## El contrato vigente

- **Fuente:** `web/common/components/Platform/Core/Http/Contract/api-errors.yaml` (23 KB).
- **Tamaño:** **136 códigos** con `http_status`, `intent` (`error|warning|info`), `retryable`
  y `message` con placeholders tipo `{nombre}`.
- **Loader:** `ApiErrorCatalog` (singleton por request, fallback `internal_error`).
- **Respuesta:** `ApiResponder` arma el JSON de error desde el catálogo.
- **Regla escrita en el propio YAML:** *"Los clientes muestran `message` tal cual."*

**Adopción actual:** 9 archivos referencian el catálogo o el responder, de los cuales sólo
4 son HTTP-facing (`BaseController`, `JsonHttpBearerAuth`, `BioenlaceApiAccessControl`,
`PacienteController`). El resto son casos de Capture que usan `ApiResponder` para errores
de dominio.

## Los tres desvíos

### 1. Controllers API — 28 literales en 16 controllers

| Controller | Literales |
|------------|-----------|
| `scheduling/TurnosController` | 4 |
| `clinical/ElectronicPrescriptionController` | 3 |
| `clinical/InpatientController` | 3 |
| `scheduling/ProfesionalAgendaController` | 3 |
| `clinical/EncounterChatController` | 2 |
| `clinical/LegalExportController` | 2 |

Ejemplo: `'message' => 'Turno cancelado'` convive en el mismo archivo con errores que sí pasan
por el catálogo. O sea: **la API habla dos idiomas** según qué rama se ejecute.

### 2. Flutter — 55 snacks con texto propio, helper usado 5 veces

- `_snack(` aparece **55 veces** entre `personalsalud/lib` y `paciente/lib`.
- `apiMessageFromJson` (que extrae `message` del JSON de error) se usa en **5** lugares.

Existe `mobile/packages/shared/lib/text/`:
`shared_texts.dart` (avisos de infraestructura) y `user_friendly_error.dart`
(`apiMessageFromJson` + mapeo de errores). La estructura está pensada para esto; la adopción
no se completó.

### 3. JS web — 38 literales en un solo archivo

`web/frontend/web/js/pacientes-listing.js` arma sus propios toasts. Es el único archivo con
este patrón en JS: el resto de la web ya usa el cliente de API compartido.

## Distinción que hay que hacer antes de tocar nada

| Tipo | Ejemplo | ¿Entra al plan? |
|------|---------|-----------------|
| **Mensaje de respuesta** | "No pudimos crear tu espacio. Intentá de nuevo." | **Sí**: debe venir del payload |
| **Validación de UX** | "Elegí el centro de salud" (bloquea el submit antes de llamar) | **Sí, parcial**: la validación local se mantiene, el **texto** sale de una fuente común |
| **Etiqueta estática** | `Text('Datos del efector')`, `Text('Volver')` | **No**: i18n/diseño |
| **Mensaje técnico** | `throw new Exception('timeout')`, `debugPrint` | **No**: no lo ve el usuario |

El auditor cuenta todo junto. Por eso un "802 en dominio" no significa 802 mensajes a mover.

## Riesgos

- **Cambiar textos visibles cambia el producto.** Mover un literal al catálogo puede alterar
  redacción y tono. La fase 02 tiene que preservar el texto exacto la primera vez (misma
  cadena, distinta fuente), y recién después unificar redacción si se quiere.
- **El cliente no puede quedar sin mensaje.** Si el payload no trae `message` (error de red,
  proxy, 502 de infraestructura), el cliente necesita un fallback local. Eso **sí** es
  responsabilidad del cliente y va en `shared_texts`.
- **`message` con placeholders**: el backend los completa; el cliente sólo muestra. Si algún
  cliente intenta formatearlos, se rompe.

## Cómo medir

```bash
cd web
C:\xampp8\php\php.exe tools/audit-thin-clients.php --report
```

Contadores involucrados: `api_controller_literal_messages`, `domain_literal_messages`,
`mobile_literal_ui_text`, `mobile_literal_ui_text_multiline`, `web_js_literal_ui_text`.

La fase 01 debe ajustar el auditor para que distinga mensaje de respuesta de etiqueta; sin
eso el baseline no sirve como criterio de cierre.
