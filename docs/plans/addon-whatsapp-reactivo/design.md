# Design — Add-on WhatsApp reactivo

## Principios

- **Sin hardcode de dominio.** La clasificación de “esto es una escritura” vive en el manifiesto del intent (`assurance: step_up`), no en un `if` del orquestador ni del canal.
- **Una sola puerta de escritura.** WhatsApp reutiliza el mismo caso de uso/API que la app; no hay un escritor paralelo.
- **Verificar antes de actuar.** La verificación se pide justo antes de ejecutar y se consume con la operación.
- **Mínimo dato.** El mensaje de WhatsApp no lleva PII ni contenido clínico; el detalle vive en la página de verificación o en la app.
- **Passkey primero.** La huella local de la app y la passkey cuestan cero; Didit es el fallback.

## Arquitectura del canal

El recorrido actual se conserva y se le insertan dos piezas:

| Etapa | Componente actual | Cambio |
|-------|-------------------|--------|
| Entrada | `WhatsAppWebhookController` | Sin cambios; ya valida firma HMAC |
| Orquestación | `WhatsAppInboundService::handleWebhookPayload` | Resuelve vínculo, aplica assurance y persiste la acción pendiente |
| Identidad | `WhatsAppIdentityService::resolveOrBootstrap` | Se reemplaza el "SI" por verificación fuerte |
| Asistente | `ChatOrchestrator::handle` | Sin cambios de dominio; recibe la sesión ya verificada |
| Salida | `WhatsAppEnvelopeRenderer` | Agrega el mensaje de step-up con enlace opaco |

El canal sigue siendo reactivo: no se agregan llamadas salientes fuera de la respuesta a un mensaje del paciente.

## Identidad y vínculo

### Estados del vínculo

| Estado | Significado |
|--------|-------------|
| `PENDIENTE_VERIFICACION` | Número conocido, sin identidad verificada |
| `ACTIVO` | Número vinculado a `user_id`/`id_persona` con verificación aprobada |
| `RECHAZADO` | El paciente rechazó o la identidad no coincide |
| `BLOQUEADO` | Demasiados intentos fallidos o abuso |
| `EXPIRADO` | Sin actividad por el período definido |

Se retira `PENDIENTE_CONFIRMACION` y el flujo de "SI".

### Datos mínimos del vínculo

- `wa_id`, `user_id`, `id_persona`, `estado`.
- `verified_at`, `verification_method` (`app_biometric`, `passkey`, `didit`), `verification_expires_at`.
- `link_token_hash`, `link_token_expires_at`.
- `failed_attempts`, `last_failed_at`.
- `consent_at`, `consent_version`, `opt_out_at`.
- `flow_session` (se conserva).

### Vinculación inicial

1. Llega un mensaje de un `wa_id` sin vínculo.
2. Se crea/actualiza el vínculo en `PENDIENTE_VERIFICACION`.
3. Se responde un texto genérico con un enlace opaco de un solo uso. No se menciona nombre, documento ni efector.
4. La página de verificación resuelve el método:
   - Si el paciente tiene la app instalada: deep link a la app para huella local.
   - Si tiene passkey: aserción WebAuthn en el origen de Bioenlace.
   - Si no: Didit hosted con `didit_paciente_biometric_workflow_id`.
5. Con la verificación aprobada se obtiene documento o `didit_reference_id`. Se busca la Persona y se compara con la identidad verificada.
6. Si hay match único y la Persona tiene usuario activo, el vínculo pasa a `ACTIVO`.
7. Si no hay match, no existe cuenta o hay ambigüedad, se responde con el texto de “usá tu propio número o instalá la app”. No se crea Persona desde este canal.

El match de `PersonaTelefono` deja de ser prueba de identidad. Como máximo puede usarse como pista para prellenar la página de verificación; nunca para vincular ni para habilitar una escritura.

### Expiración y reintentos

- El enlace de vinculación vence a los 10 minutos y es de un solo uso.
- Máximo de intentos fallidos por `wa_id` antes de `BLOQUEADO`.
- Vínculo inactivo por 90 días pasa a `EXPIRADO` y exige re-vinculación.
- Si el número ya está vinculado a otra Persona, no se permite re-vincular sin revisión asistida.

## Step-up y assurance

### Escalera de métodos

| Orden | Método | Costo marginal | Requisito |
|-------|--------|----------------|-----------|
| 1 | Huella local de la app | 0 | App instalada y sesión de paciente |
| 2 | Passkey WebAuthn | 0 | Passkey registrada en el origen de Bioenlace |
| 3 | Didit biométrico | ~USD 0,18 | Workflow biométrico configurado |

El paciente puede no tener passkey: se ofrece registrarla después de la primera verificación exitosa, para que las próximas escrituras sean gratis.

### Token de step-up

- La página de verificación emite un token opaco, de un solo uso, con TTL de 10 minutos.
- El token se guarda hasheado en el servidor y se vincula a `wa_id`, `id_persona` y a la acción pendiente.
- La operación pendiente se guarda con su ruta, cuerpo y hash del draft; no se confía en lo que el paciente reescriba en el chat.
- Al volver a WhatsApp, el sistema consume el token, compara la identidad y ejecuta la operación una sola vez.

### Passkey

- Se reutiliza `PasskeyAuthenticationService::authenticationOptions` y `authenticate`, con un propósito nuevo `whatsapp-stepup`.
- La página de step-up debe vivir en el mismo origen/RP ID que las passkeys existentes (`app.bioenlace.io`), para que el navegador encuentre la credencial.
- La aserción devuelve el `User`; se compara con el `user_id` del vínculo. Si no coincide, se rechaza.
- No se inicia sesión web: el step-up solo habilita la operación de WhatsApp.

### Didit

- Se crea una sesión hosted con `vendor_data` opaco (sin PII).
- Al aprobarse, `getBiometricAuth` devuelve `linked_document` o `didit_reference_id`.
- Se compara con la Persona del vínculo. Si no coincide, no se ejecuta.
- Se registra `verification_id`, método, resultado y expiración para auditoría.

## Gate de escrituras

### Clasificación de contenido

Además de la assurance, cada intent declara si puede responderse por WhatsApp (`whatsapp: allowed`). El default es bloqueado. Los intents clínicos no se declaran y el canal responde con un enlace a la app. Esto evita que un cambio futuro en el asistente exponga datos clínicos por WhatsApp sin decisión explícita.

### Clasificación de escrituras

- Cada intent mutante declara `assurance: step_up` en su manifiesto (carpetas `create`, `update`, `delete`).
- Un test de arquitectura recorre los intents y falla si uno de escritura no declara assurance.
- Los intents de `read` no lo declaran.

### Punto de control

Hay dos capas, por defensa en profundidad:

1. **Canal WhatsApp**: antes de continuar un intent o callback mutante, `WhatsAppInboundService` exige un step-up vigente. Si no lo hay, guarda la acción pendiente y responde con el enlace.
2. **API de escritura**: la ruta que ejecuta el `flow_submit` verifica la assurance de la sesión. Si el cliente es `whatsapp-paciente`, exige el token de step-up; si es la app o la web, usa su sesión normal.

La regla de assurance no se duplica por ruta: se declara una vez en el manifiesto del intent y se resuelve por `intent_id`.

### Flujo de una escritura

1. El paciente pide “cancelar mi turno”.
2. El asistente arma el draft y llega al paso de confirmación.
3. El canal detecta `assurance: step_up` y que no hay token vigente.
4. Guarda la acción pendiente y responde con el enlace de verificación.
5. El paciente verifica y vuelve con el token.
6. El canal consume el token, valida identidad y ejecuta el `flow_submit` una sola vez.
7. Responde el resultado. Si la identidad no coincide, no ejecuta y explica.

### Operaciones que no se pueden completar por WhatsApp

Si un paso del flow requiere `client_open` (calendario, selector rico), el canal ya responde con deep link a la app. Esas operaciones quedan fuera del MVP de escritura por WhatsApp; el step-up se exige igual cuando la operación se ejecute desde la app.

## Modelo de datos

### Tablas nuevas o ampliadas

| Tabla | Uso |
|-------|-----|
| `asistente_whatsapp_vinculo` | Se amplía con estado, verificación, token de vinculación, intentos y consentimiento |
| `asistente_whatsapp_verificacion` | Auditoría de cada step-up: `wa_id`, `id_persona`, método, `verification_id`, estado, `expires_at`, `consumed_at` |
| `asistente_whatsapp_accion_pendiente` | Acción mutante esperando step-up: ruta, cuerpo, hash del draft, token, expiración, estado |

Migraciones nuevas; no se edita `m260711_150000_asistente_whatsapp_vinculo`.

### Idempotencia

- El `wamid` ya se deduplica en `asistente_whatsapp_mensaje`.
- El token de step-up y la acción pendiente se consumen en una transacción.
- Reintentos de Meta o del paciente no duplican la operación.

## Seguridad y privacidad

- No revelar nombre ni datos antes de verificar. El prompt de vínculo es genérico.
- No incluir PII ni datos clínicos en el texto del mensaje ni en la URL; solo un token opaco.
- Comparar siempre la identidad verificada con la Persona vinculada.
- Rate limit por `wa_id` y por IP de la página de verificación.
- Bloqueo temporal tras N fallos.
- Auditoría de cada acceso y de cada escritura.
- Consentimiento y baja registrados.
- Sin datos clínicos: si el paciente pide un resultado, se responde con enlace a la app.
- Revisar el marco legal y la transferencia internacional antes de habilitar producción: [costos-whatsapp.md](../../negocio/costos/costos-whatsapp.md).

## Costos y add-on

### Qué se cobra

- **Meta**: mensajes service después de 1.000 por número por mes. Sin plantillas proactivas.
- **Didit**: solo cuando el paciente no tiene app ni passkey.
- **Passkey y huella local**: costo marginal cero.

### Supuestos de planificación

| Parámetro | Valor inicial |
|-----------|---------------|
| Mensajes service por atención | 1,0 |
| Escrituras por atención | 0,1 |
| Proporción sin passkey/app | 0,5 |
| Costo Didit por step-up | 0,18 USD |
| Tarifa service de referencia | 0,026 USD |

COGS por atención: `1,0 × 0,026 + 0,1 × 0,5 × 0,18 = 0,035 USD`, equivalente a **~USD 17,5 por profesional/mes** a 500 atenciones. Queda por debajo del umbral de USD 26 que el producto considera prohibitivo. Si la proporción sin passkey baja, el costo baja.

### Add-on

- `id`: `whatsapp`.
- Estado: `optional` en `AMB`, `EMER` e `IMP`.
- `plan_field`: `whatsapp_permitido`.
- COGS declarado en `cogs_usd_per_encounter['whatsapp']` y supuestos en `cogs_assumptions`.
- El add-on incluye el canal y el step-up; no incluye IA del chat, que sigue en §1.

### Entitlement

- El add-on se persiste con `addonPlanFields` y `SignUpInstitutionalEfector`.
- La cotización lo toma por `LicenciaController::actionCotizar`.
- El canal resuelve el efector del paciente y verifica que tenga el add-on. Si no lo tiene, responde que el canal no está habilitado para ese centro y ofrece la app.
- Decisión pendiente: qué hacer si el paciente tiene varios efectores con estados distintos.

## Observabilidad

- Registrar cada mensaje saliente con categoría, `billable` y `pricing.type` del webhook de estado.
- Registrar cada step-up con método, resultado, `verification_id`, costo estimado y si consumió crédito Didit.
- Métricas por efector y por mes: mensajes, step-ups, costo Meta, costo Didit.
- Alertas: tasa de fallos de verificación, bloqueos, vínculos expirados.

## Alternativas descartadas

| Alternativa | Por qué no |
|-------------|------------|
| Vínculo por "SI" | No prueba identidad; el número puede ser de otra persona |
| OTP por WhatsApp | Prueba posesión del número, no identidad; vulnerable a SIM swap |
| TTL largo | Un teléfono perdido queda habilitado demasiado tiempo |
| Datos clínicos por WhatsApp | Riesgo legal y de privacidad; el dato clínico va a la app |
| Plantillas proactivas | Costo por mensaje y aprobación de Meta; fuera de alcance |
