# Costos — WhatsApp proactivo y verificación de identidad

**Tipo:** costos · análisis de producto
**Última actualización:** 2026-11-12
**Alcance:** add-on opcional de WhatsApp (avisos proactivos + asistente), todos los tipos de atención, y el costo de re-verificar identidad con Didit antes de entregar datos sensibles. **No** incluye la IA del asistente: ese COGS ya vive en [costos-api.md §1](costos-api.md#1-conversación-con-el-paciente-chat-asistente) y no se duplica por canal.

---

## Resumen ejecutivo

| Pregunta | Respuesta orientativa |
|----------|------------------------|
| ¿Se puede cobrar como add-on opcional? | **Sí.** Igual que videollamada: `optional`, el cliente lo contrata o no. |
| ¿Qué domina el costo? | **Meta**, no la IA. Cada mensaje proactivo cuesta por mensaje; 3–6 mensajes por atención superan varias veces el COGS de IA de esa atención. |
| ¿El asistente reactivo sigue gratis? | **Ya no.** Desde el 1/10/2026 Meta cobra los mensajes **service** después de **1.000 por número por mes**. |
| ¿Cuánto cuesta verificar identidad? | **~USD 0,18** por step-up biométrico (liveness + face match + device), no 0,10 como decía el doc viejo. El cupo gratis pasó de 500 sesiones a **USD 10 de crédito/mes** (~30 KYC o ~55 biometrías). |
| ¿El vínculo teléfono↔paciente alcanza? | **No.** Hoy es un match de `PersonaTelefono` + respuesta "SI". No prueba que del otro lado esté el paciente: número reciclado, teléfono compartido, SIM swap. |
| ¿Dónde entregar datos clínicos? | **En la app** (huella local, costo cero). WhatsApp para lo transaccional y, si hay que mostrar algo clínico, con step-up Didit de corta duración. |
| ¿Una ley obliga a cifrado extremo a extremo? | **No.** La Ley 25.326 exige medidas de seguridad y confidencialidad (arts. 9 y 10), no E2E. Pero Cloud API **no** es E2E y Meta procesa el contenido: para salud sigue siendo un tercero en el exterior (art. 12). |
| ¿La cuenta cierra con cost-plus? | **No con volumen pleno.** Acotado a reactivo sin datos clínicos y con las escrituras en la app, el COGS queda en **USD 0,0293/atención (~USD 14,65/prof/mes)**: por debajo del umbral de USD 26. |

---

## 1. Decisión de producto

- El add-on se contrata **opcionalmente**, como videollamada.
- Aplica a **todos los tipos de atención**: el chat del paciente no es exclusivo de ambulatorio.
- El add-on habilita: avisos proactivos por plantilla, asistente reactivo y los enlaces de verificación de identidad.
- Sin add-on: se mantiene **push** y el asistente dentro de la app.

**Hallazgo a reconciliar antes de implementar:** el catálogo de precios declara `includes_patient_chat = true` solo para `AMB` y `false` para `EMER`/`IMP`, y hay un test que fija eso. Si el chat está disponible para todos, ese dato del catálogo quedó atrás y el costo de chat se está atribuyendo solo a ambulatorio.

---

## 2. Qué cobra Meta hoy (vigente 1/10/2026)

Meta pasó a **cobro por mensaje entregado**. No cobra los mensajes que el usuario le envía a la empresa.

| Categoría | Cuándo se usa | ¿Se cobra? |
|-----------|---------------|------------|
| **Service** | Mensaje libre dentro de la ventana de 24 h que abre el paciente | Después de **1.000 mensajes service gratis por número por mes**; el excedente sí se cobra |
| **Utility** | Plantilla transaccional/informativa que inicia la empresa | Sí, por mensaje |
| **Authentication** | Plantilla de código de verificación | Sí, por mensaje |
| **Marketing** | Plantilla promocional | Sí, por mensaje (la más cara) |
| **Business Agent** | Respuesta no-plantilla motorizada por Meta | Sí, por tokens; no aplica a nuestro caso |

Puntos que cambian el cálculo respecto del doc anterior:

1. **La ventana de 24 h ya no es gratis.** El único alivio es el cupo de 1.000 service por número, que con un número compartido por todos los efectores es irrelevante.
2. **Las plantillas se aprueban antes.** Utility no permite copy libre: el contenido es fijo y revisado. Un "resultado de laboratorio" no puede ir como texto arbitrario; va como aviso + enlace a la app.
3. **Tiers de volumen** para utility y authentication: descuentos por mercado–categoría, acumulados a nivel portfolio. No aplican a service.
4. **FEP** (ventana de punto de entrada gratuito): solo si el usuario llega desde un anuncio click-to-WhatsApp. No es nuestro caso.
5. **La moneda no se puede cambiar** después de crear la cuenta (ARS está disponible desde abril 2026). Conviene abrir la cuenta en **USD**.

### Tarifa de referencia

El repo venía usando **~USD 0,026 por plantilla utility Argentina** (rate card de octubre 2025). La tarifa vigente cambió el 1/10/2026 y hay que validarla en el rate card por mercado (ARS y USD). Todo este análisis es lineal en ese valor, por eso los escenarios se muestran a **USD 0,007 / 0,013 / 0,026** por mensaje.

**Pregunta abierta de alto impacto:** verificar en Billing si una plantilla **utility enviada dentro de la ventana de 24 h** sigue siendo gratuita. Según la página de desarrolladores vigente, el único beneficio automático es el cupo de 1.000 service; la página comercial todavía repite "no cobramos utilidad en respuesta al usuario". La diferencia cambia el costo de las respuestas del asistente.

### Un número por efector o uno compartido

| Modelo | Efecto en costo |
|--------|-----------------|
| **Un número compartido** | El cupo de 1.000 service/mes se consume una vez; los tiers se acumulan a favor; pero la marca es Bioenlace y el costo no se puede atribuir por efector |
| **Un número/WABA por efector** | Cada efector tiene sus 1.000 service gratis y sus plantillas aprobadas; requiere alta, verificación y billing por cliente |

Para cobrar el add-on por efector, el segundo modelo es el que permite atribuir consumo. Decisión pendiente.

---

## 3. Inventario de mensajes proactivos

Los textos ya están en catálogos; lo que falta es el **presupuesto de mensajes** por atención.

| Origen (catálogo) | Aviso | Frecuencia típica |
|-------------------|-------|-------------------|
| `turno-lifecycle-notifications.yaml` | Recordatorio de turno | 1–2 por turno |
| Idem | Cancelación por el consultorio, demora | Solo si ocurre |
| `turno-antinoshow-notifications.yaml` | Confirmá tu turno, turno liberado, adelantar | 1–2 por turno |
| `turno-resolution-*.yaml` | Reubicación, shortlist, auto-reserva | Solo si ocurre |
| `reserva-triage-post-cupo-notifications.yaml` | Ruteo post-cupo | Solo si ocurre |
| `care-followup-touchpoint-notifications.yaml` | Seguimiento post-atención | 1–3 por episodio |
| `encounter-patient-notifications.yaml` | Resumen publicado | 1 por atención, si el paciente lo pide |
| `post-lab-classification-notifications.yaml` | Resultado de laboratorio | 1 por estudio |
| `encounter-async-*.yaml` | Alertas operativas de agenda | No van al paciente |

Como el add-on se cobra por uso, el **límite de mensajes por atención es la palanca de costo**, no un detalle. Propuesta de presupuesto:

| Escenario | Utility/atención | Service/atención | Step-up Didit/atención |
|-----------|------------------|------------------|------------------------|
| **A — mínimo** | 2 (confirmación + recordatorio) | 1 | 0,10 |
| **B — base** | 4 (+ cambio/demora + seguimiento) | 2 | 0,25 |
| **C — intensivo** | 6 (+ anti no-show + resultado) | 4 | 0,50 |

El step-up se expresa por atención porque el modelo de precios es por atención; en la realidad se factura por verificación (ver §5).

---

## 4. Modelo de costos

Parámetros:

| Símbolo | Concepto | Valor de planificación |
|---------|----------|------------------------|
| `r_u` | USD por plantilla utility entregada | 0,007 / 0,013 / 0,026 (sensibilidad) |
| `r_s` | USD por mensaje service después del cupo | Igual a `r_u` hasta validar Billing |
| `c_d` | USD por step-up biométrico Didit | **0,18** = liveness pasivo 0,10 + face match 0,05 + device/IP 0,03 |
| `N` | Atenciones por profesional/mes | 500 |
| `M_u`, `M_s`, `V` | Mensajes y verificaciones por atención | Según escenario |

Costo por atención:

> `COGS/atención = M_u × r_u + M_s × r_s + V × c_d`

Costo por profesional/mes: `COGS/atención × N`.

Margen de lista del catálogo: **233 % sobre costo** (precio = COGS × 3,33). Los add-ons opcionales usan su propio margen (`addon_margin_on_cost_percent`), separado del de la licencia.

---

## 5. Escenarios de costo

### Por profesional/mes (500 atenciones)

| Escenario | COGS/atención @ 0,007 | @ 0,026 | USD/prof/mes @ 0,007 | @ 0,026 |
|-----------|----------------------|---------|----------------------|---------|
| **A** (2 u, 1 s, 0,10 v) | 0,039 | 0,096 | **~20** | **~48** |
| **B** (4 u, 2 s, 0,25 v) | 0,087 | 0,201 | **~44** | **~101** |
| **C** (6 u, 4 s, 0,50 v) | 0,160 | 0,350 | **~80** | **~175** |

### Qué implica para el precio del add-on

Aplicando el margen de lista (×3,33) sobre el escenario B @ 0,026: **~USD 0,67 por atención**, o **~USD 335 por profesional/mes**. Es un orden de magnitud que no se sostiene como add-on si se cobra sobre el volumen pleno de avisos.

Conclusión: el add-on no puede ser "todos los avisos de todos los pacientes a cost-plus". Opciones:

1. **Presupuesto incluido + excedente**: N mensajes por atención incluidos (por ejemplo 3), el resto se factura por paquete. Es lo único que mantiene el precio predecible.
2. **Cobro por mensaje/paquete** independiente del tipo de atención (el catálogo actual es por atención y no lo modela).
3. **Restringir el alcance**: solo avisos de turno (2 mensajes), y el resto sigue en push.

### ¿El costo es prohibitivo?

Depende del alcance. Conviene separar tres cosas que hoy se están mezclando:

| Alcance | Costo Meta | Lectura |
|---------|------------|---------|
| **Solo transaccional** (2 avisos de turno, sin datos clínicos, sin step-up) | ~USD 0,05/atención → **~USD 26/prof/mes** @ 0,026 | **No es prohibitivo.** Es un add-on acotado y predecible |
| **Proactivo amplio** (4–6 avisos + service) | ~USD 0,15–0,26/atención → **~USD 75–130/prof/mes** | **Sí** como cost-plus sobre volumen pleno |
| **Clínico por WhatsApp** (aviso + step-up Didit) | Suma **~USD 0,18 por verificación** y el riesgo legal | **Sí**, y además es la opción más riesgosa |

El costo prohibitivo aparece cuando se intenta llevar **todo** por WhatsApp. La versión viable es **transaccional y sin contenido clínico**, con lo clínico en la app. En ese recorte, Didit no forma parte del add-on y el número vuelve a ser manejable.

### Si USD 26/prof/mes ya es prohibitivo

Entonces el add-on no puede absorber el costo de Meta. Caminos posibles, de menor a mayor cambio:

1. **BYO WhatsApp (WABA del efector)**: el efector usa su propio número y paga Meta directo; Bioenlace cobra solo el software (automatización, plantillas, asistente). El COGS de Meta sale de nuestra estructura.
2. **Paquetes prepagos de mensajes**: el efector compra un bloque y consume; sin consumo no hay costo ni precio. Requiere medir por número/WABA.
3. **Bajar a 1 mensaje por atención**: solo el recordatorio de mayor valor (por ejemplo, el de mayor riesgo de ausencia). A 0,026 son ~USD 13/prof/mes; a 0,007, ~USD 3,5.
4. **No hacer proactivo**: dejar WhatsApp solo reactivo. Ojo: el cupo de 1.000 service gratis es por número y **no cubre plantillas utility**; las plantillas proactivas siempre se pagan.
5. **Volver a push como canal primario** y usar WhatsApp solo para casos de alto impacto.

Si ninguna cierra, la conclusión honesta es que **el WhatsApp proactivo no es un add-on viable con Meta pagado por Bioenlace**; sí puede serlo como integración sobre la WABA del cliente.

### Didit — step-up por verificación

Con **USD 10 de crédito/mes** (≈ 55 biometrías a 0,18 o ≈ 30 KYC a 0,33):

| Verificaciones/mes | Costo biometría @ 0,18 | Costo KYC @ 0,33 |
|--------------------|------------------------|------------------|
| 50 | **0** | ~7 |
| 100 | **~8** | ~23 |
| 1.000 | **~170** | ~320 |
| 5.000 | **~890** | ~1.640 |
| 10.000 | **~1.790** | ~3.290 |
| 50.000 | **~8.990** | ~16.490 |

Factores que mueven esto:

- **El precio nuevo ya no es pay-per-success puro**: una sesión rechazada o abandonada puede contener checks facturables. Hay que medir abandonos.
- **La verificación se factura por sesión, no por paciente.** Por eso el TTL importa: sin TTL, cada mensaje sensible dispara una sesión.
- **Plan pago**: Growth (USD 99/mes) da 4 % de descuento y USD 115 de crédito; Scale (USD 299/mes) da 10 % y USD 350. Se justifica recién con volumen alto.
- **Didit Authentication** (login hosted, passkeys, códigos) es otro producto y anuncia 50.000 usuarios/mes gratis en el plan Free. No reemplaza FaceID, pero puede ser una alternativa más barata si se acepta passkey en la app.

---

## 6. TTL: qué es y por qué define el costo

**TTL** (*time to live*) es la **ventana de validez de una verificación**. Después de un step-up exitoso se guarda en el vínculo:

- `verified_at`: cuándo se verificó,
- `verification_id`: qué sesión de Didit lo aprobó,
- `expires_at`: hasta cuándo vale.

Dentro de esa ventana, los pedidos sensibles no vuelven a pedir Didit. Al vencer, el próximo pedido sensible exige una verificación nueva.

| TTL | Efecto en costo | Efecto en riesgo |
|-----|-----------------|------------------|
| Sin TTL (verificar cada mensaje) | 1 sesión Didit por mensaje sensible; el costo se dispara | Mínimo |
| **15 min** | ~1 verificación por conversación | Buen equilibrio para datos de salud |
| 30 min | Menos verificaciones | Aceptable para trámites |
| 24 h o más | Casi gratis | Un teléfono perdido sigue verificado todo el día |

Recomendación: **15 minutos para información clínica y 30 para administrativa**, con invalidación inmediata por "cerrar sesión", N intentos fallidos o cambio de número. El TTL se guarda por `wa_id`, no por usuario: si el paciente escribe desde otro número, es otra sesión.

Alternativa más barata y más fuerte: si el pedido es clínico, **no verificar en WhatsApp**: responder con un enlace a la app y que la app use la huella local (costo cero). Didit queda para quien no tiene la app o para trámites que no pueden salir del canal.

---

## 7. Seguridad y privacidad

### El vínculo actual no prueba identidad

El flujo vigente busca el teléfono en `PersonaTelefono`, y si hay un único match pregunta "¿Sos <nombre>? Respondé SI". Eso prueba que alguien controla el número, no que sea el paciente. Riesgos concretos:

| Riesgo | Por qué |
|--------|---------|
| **Número reciclado** | Las operadoras reasignan números; el vínculo queda activo para un tercero |
| **Teléfono compartido** | Familiar o cuidador responde "SI" y accede a todo |
| **SIM swap** | Portabilidad fraudulenta del número |
| **Número mal cargado** | Alta por staff con un teléfono que no es del paciente |
| **Fuga en el propio prompt** | "¿Sos <nombre>?" revela a quien controla el número que ese teléfono pertenece a un paciente de Bioenlace y cuál es su nombre |
| **Vínculo eterno** | No expira por inactividad ni por cambio de teléfono |
| **Sin límite de intentos** | No hay bloqueo por reintentos ni auditoría de accesos |

### Clasificación de datos por canal

| Nivel | Ejemplos | Canal |
|-------|----------|-------|
| **Bajo** | Existencia de un turno, fecha, hora, efector, dirección | WhatsApp sin step-up |
| **Medio** | Estado de un trámite, si un estudio está disponible (sin resultado) | WhatsApp con enlace a la app |
| **Alto** | Resultado, diagnóstico, medicación, resumen, cualquier dato que revele una condición | **App con huella local**; si no es posible, step-up Didit con TTL de 15 min |
| **Crítico** | Descarga de historia clínica, representación legal, cambios de datos de contacto | Solo app/web autenticada; nunca dentro de WhatsApp |

### Marco legal: qué obliga y qué no

**Corrección importante:** en Argentina **no hay una ley que exija cifrado extremo a extremo** para comunicaciones de salud. Lo que hay es un deber de seguridad y confidencialidad, y reglas específicas para datos sensibles. El cifrado E2E es una medida posible, no un requisito literal.

| Norma | Qué exige | Qué implica acá |
|-------|-----------|-----------------|
| **Ley 25.326, art. 7.2** | Los datos sensibles solo se tratan con **razones de interés general autorizadas por ley**, o con fines estadísticos/científicos disociados | El consentimiento solo no alcanza para datos sensibles; el tratamiento se apoya en la habilitación del art. 8 |
| **Ley 25.326, art. 8** | Los establecimientos de salud y profesionales pueden tratar datos de salud **respetando el secreto profesional** | Es la base legal del tratamiento clínico, no del canal |
| **Ley 25.326, art. 9** | Medidas técnicas y organizativas **necesarias** para seguridad y confidencialidad | El estándar es "adecuado al riesgo", no "E2E" |
| **Ley 25.326, art. 10** | Deber de confidencialidad de todos los que tratan datos | Alcanza a empleados, proveedores y encargados |
| **Ley 25.326, arts. 5 y 6** | Consentimiento libre, expreso e informado, y deber de informar finalidad y destinatarios | Un "SI" por WhatsApp no es consentimiento informado |
| **Ley 25.326, art. 12** | Prohíbe transferir datos a países sin protección adecuada, salvo excepciones | Meta procesa en EE. UU.: hay que documentar la base legal |
| **Ley 26.529** | Confidencialidad de la historia clínica | Refuerza el deber, no impone un canal |
| **Código Penal, art. 156** | Secreto profesional | Aplica a profesionales y a quien acceda indebidamente |

**Cómo se traduce en el canal:**

- **WhatsApp Cloud API no es extremo a extremo.** Viaja cifrado en tránsito (TLS) y Meta lo cifra en reposo, pero **Meta puede acceder al contenido** para operar y moderar. Es distinto de la app WhatsApp Business "de teléfono", que sí usa el cifrado E2E del consumidor; nosotros usamos la plataforma Cloud API.
- Por eso, mandar datos clínicos por Cloud API no es "ilegal" por sí solo, pero suma a Meta como tercero que trata datos sensibles fuera del país. Es una decisión que exige base legal, contrato de tratamiento y evaluación de riesgo; **no es un default aceptable**.
- **Cifrado no es igual a cumplimiento.** Aunque el canal fuera E2E, seguirían faltando consentimiento informado, finalidad, control de acceso, auditoría y retención.
- La app no elimina la obligación de seguridad, pero deja el dato bajo nuestro control: TLS, base propia, acceso con huella local y auditoría.

**Otros requisitos del canal:** opt-in previo por paciente (registrado con fecha y canal), baja explícita, y copy de plantilla sin contenido clínico ("tenés un resultado disponible", no el resultado).

### ¿Es ilegal usar Cloud API? ¿Alcanza con un botón de acepto?

**No es ilegal por sí mismo.** La ley no prohíbe WhatsApp ni Cloud API. Lo que regula es el tratamiento de datos personales y, con más exigencia, los sensibles.

- Para **avisos transaccionales sin contenido clínico** (turno, fecha, efector, "tenés un resultado disponible"), el riesgo es bajo. Con opt-in, información al paciente y contrato con el proveedor, es defendible.
- Para **contenido clínico** (resultado, diagnóstico, medicación), el problema no es el canal en abstracto sino la combinación: dato sensible (art. 7.2), tratamiento habilitado por el art. 8, deber de seguridad (arts. 9 y 10) y **transferencia internacional a un país sin protección adecuada** (art. 12), porque Meta procesa fuera del país.

**Un botón "acepto" no lo soluciona**, por tres razones:

1. **No es la base legal del dato sensible.** El art. 7.2 no habilita los datos sensibles por consentimiento: exige razones de interés general autorizadas por ley. La habilitación del tratamiento clínico es el art. 8 (establecimiento o profesional con secreto), no el botón.
2. **No está entre las excepciones del art. 12.2.** El consentimiento no figura como excepción a la prohibición de transferir a países sin protección adecuada. Hay doctrina que lo discute, pero no es una posición pacífica.
3. **No cubre el resto.** No evita que un tercero con acceso al teléfono lea el mensaje, ni resuelve la identidad, ni reemplaza medidas de seguridad, auditoría y retención.

**Para qué sirve el consentimiento:** para informar (art. 6), documentar el opt-in del canal y sostener una cesión si se la encuadra en el art. 11. Es necesario, pero **no suficiente**.

Conclusión práctica: si el mensaje no contiene datos clínicos y solo invita a abrir la app, es defendible. Si contiene el dato clínico, necesita dictamen legal, contrato de tratamiento y evaluación de riesgo; aun así el art. 12 sigue siendo un problema.

### Política propuesta

1. No revelar el nombre en el prompt de vinculación; usar un texto genérico.
2. Step-up Didit hosted por enlace, con callback firmado y TTL corto.
3. Datos clínicos solo con verificación vigente o derivación a la app.
4. Expirar vínculos inactivos (por ejemplo 90 días) y exigir re-vinculación.
5. Límite de intentos y bloqueo temporal.
6. Auditoría de cada acceso sensible: `wa_id`, qué se pidió, `verification_id`, resultado.
7. Consentimiento y baja explícitos, con registro.

---

## 8. Diseño del add-on (propuesta)

| Atributo | Valor propuesto |
|----------|-----------------|
| `id` | `whatsapp` |
| Estado | `optional` en todas las clases (`AMB`, `EMER`, `IMP`) |
| `plan_field` | `whatsapp_permitido` |
| COGS | USD 0,0293/atención: 1 mensaje service + vinculación Didit amortizada. Sin Didit por escritura (la hace la app). |
| Incluye | Avisos reactivos, asistente, enlaces de vinculación |
| No incluye | IA del chat (ya en §1), datos clínicos, escrituras (se completan en la app) |

Dos formas de llevarlo al catálogo, a decidir:

1. **COGS por atención** (consistente con videollamada): `cogs_usd_per_encounter['whatsapp']` calculado desde mensajes/atención × tarifa + verificaciones/atención × costo. Simple, pero produce precios altos y no refleja que el consumo no es proporcional a las atenciones.
2. **Presupuesto de mensajes + excedente**: el add-on incluye un bloque de mensajes por atención y el excedente se factura. Requiere extender el modelo de precios, que hoy solo conoce COGS por atención.

Recomendación: empezar por (1) con un presupuesto acotado (2 mensajes utility + 1 service + step-up con TTL), y migrar a (2) cuando haya telemetría real de mensajes por atención.

> **Recorte final:** el escenario B de §5 (~USD 0,20/atención) asumía Didit por escritura. Con el diseño implementado —escrituras derivadas a la app, que hace el step-up con huella/passkey— el COGS baja a **USD 0,0293/atención (~USD 14,65/prof/mes)**, por debajo del umbral de USD 26. No hay Didit por escritura.

---

## 9. Qué instrumentar antes de poner precio

Sin estos datos, cualquier número es una suposición:

- **Por mensaje saliente**: categoría, plantilla, `billable`, `pricing.type` (`free_customer_service` vs `regular`) y `pricing.category` del webhook de estado.
- **Por verificación Didit**: `verification_id`, resultado, si hubo abandono, si fue dentro del crédito.
- **Denominadores**: atenciones, pacientes activos, conversaciones, mensajes por conversación.
- **Opt-in/opt-out** por paciente.
- **Costo atribuido por efector y por número/WABA.**

Con eso se recalibran los escenarios y se decide si el add-on va por atención o por paquete de mensajes.

---

## 10. Preguntas abiertas

1. ¿La plantilla utility dentro de la ventana de 24 h sigue siendo gratuita? (alto impacto)
2. ¿Un número compartido o uno por efector?
3. ¿El add-on se cobra por atención o por paquete de mensajes?
4. ¿El step-up es Didit FaceID, passkey de la app o ambos según el caso?
5. ¿Se acepta que Meta procese avisos con datos clínicos, o solo avisos sin contenido clínico?
6. ¿Qué pasa con el cupo gratuito de 500 sesiones que asumía el doc de Didit? Ya no existe: ver [costos-didit.md](costos-didit.md).

---

## Referencias

- [Meta — Pricing on the WhatsApp Business Platform](https://developers.facebook.com/docs/whatsapp/pricing/) — cobro por mensaje, cupo service, tiers, FEP (vigente 1/10/2026).
- [Didit — Pricing](https://didit.me/pricing/) — crédito mensual y precios por módulo (vigente 1/11/2026).
- [Didit — Authentication](https://docs.didit.me/authentication/overview) — producto de login hosted/passkeys.
- [costos-api.md §7](costos-api.md#7-whatsapp-cloud-api-paciente) — estado actual del alcance reactivo.
- [costos-didit.md](costos-didit.md) — proyección de identidad (tarifas a recalibrar).
- [producto/asistente-y-chat.md](../../producto/asistente-y-chat.md) — alcance del asistente y WhatsApp.
- [producto/turnos.md](../../producto/turnos.md) — notificaciones y decisión de push vs WhatsApp.
