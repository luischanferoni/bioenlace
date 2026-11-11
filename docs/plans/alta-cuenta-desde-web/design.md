# Design — Alta de cuenta desde la web con el teléfono

## Decisiones cerradas

| Tema | Decisión |
|------|----------|
| Canal | El alta vive en el frontend Yii (`/alta`). La identidad se verifica en el teléfono con Didit hosted; la computadora nunca usa cámara ni sube documentos |
| Orden del recorrido | Elegir centro → QR → código → Didit en el teléfono → alta. El centro elegido se guarda en el vínculo y se revalida al crear la cuenta |
| QR | Contiene sólo `…/alta/vinculo/<token>`. Sin datos personales, sin efector, sin código |
| Token | Opaco, se guarda hasheado, se consume al reclamarse desde el teléfono, TTL corto |
| Código de emparejamiento | 6 caracteres de un alfabeto sin ambigüedad, comparado por hash, máximo 5 intentos, se muestra sólo en el navegador que inició el vínculo y sólo después de que el teléfono abrió la URL |
| Autoridad | El servidor consulta Didit (`DiditIdentityService::fetchApprovedVerification`) y nunca confía en el `verification_id` que envía un cliente |
| Reutilización | El alta usa `CreateIdentityAccount` + `StaffAffiliationRequestService`, los mismos que `POST /api/v1/licencia/crear-cuenta`; no se duplican reglas |
| Sesión web | Al terminar el alta de cuenta personal no se crea sesión: queda con perfil Paciente y la solicitud pendiente. El acceso web sigue siendo por aprobación desde la app (`QrLoginService`) y `StaffMobileAccessService` no se toca; el caso del titular que compra un espacio se decide aparte en la fase 06 |
| Idempotencia | La garantiza la identidad (lock por documento + fecha de nacimiento) y el store de intentos que ya usa el alta; reintentar el último paso no duplica nada |
| Persistencia | Tabla nueva `signup_pairing` por migración idempotente; estados en ENUM con las constantes del AR `SignupPairing` como única fuente (convención `migrations-enum-columnas`) |
| Borrador | Vive **sólo en el servidor**: `commercial_signup_draft` por persona (ya existe) para lo persistente y la fila del vínculo para lo transitorio previo a la identidad. La web no guarda nada en `localStorage`, `sessionStorage` ni cookies |
| Reanudación | Reabrir `/alta` y volver a verificar la identidad devuelve el borrador con `current_step`; el borrador no depende de que el vínculo siga vivo |
| E-mail | El alta de cuenta profesional (gratuita) sigue sin exigirlo. El alta paga lo exige **válido y confirmado antes del checkout**, con los endpoints de envío y estado que ya existen |
| Aviso de pago | Al confirmarse el pago se envía un aviso al e-mail confirmado del titular (copy en catálogo YAML del dominio de facturación), idempotente por pago. Hoy no existe |
| Acceso tras el pago | Gratis primero: la URL del QR abre la app (huella local) o se entra con **passkey del teléfono** (sirve en computadoras compartidas, no deja nada en el equipo); si no tiene ninguna, **recuperación asistida** (ya existe, con aprobación de superadmin y enrolamiento de passkey). Didit sale del login: se paga una vez en el alta |
| App obligatoria | **No.** Con passkey del teléfono el usuario entra sin instalar nada. La app sigue siendo el camino más rápido y el recomendado para quien atiende, pero no es requisito de acceso |
| Passkey en v1 | Sí: el ingreso con passkey del teléfono entra en la primera versión, no queda como mejora posterior |
| Duración de sesión | Corta, sin "recordar este navegador": como el ingreso deja de costar, no hace falta amortizar nada y un equipo compartido no conserva acceso |
| Alcance comercial | La compra de espacio de atención desde la web entra en las fases 05–06; el checkout, los precios y la activación por webhook no se reescriben |

## Borrador y reanudación en la web

La app móvil parece guardar el borrador "en el teléfono", pero el borrador real ya es del servidor: `commercial_signup_draft` guarda por persona el paso actual, el efector, el plan, el e-mail del pagador y una fecha de vencimiento, y `guardar-borrador` lo reescribe revalidando la prueba Didit. Lo que la app guarda localmente es el `verification_id`, para no repetir Didit tras un fallo de red.

En consecuencia, la web no necesita almacenamiento temporal en el navegador:

| Qué | Dónde vive | Cuánto dura |
|-----|------------|-------------|
| Centro elegido y estado del vínculo antes de que exista la persona | Fila `signup_pairing` | Minutos (TTL del vínculo) |
| Alta comercial en curso (paso, efector, plan, e-mail) | `commercial_signup_draft` | Según su `expires_at` |
| `verification_id` | Fila `signup_pairing`; nunca el navegador | TTL del vínculo, reutilizable al reintentar |

Reanudar es volver a acreditar identidad: se abre `/alta`, se repite el vínculo y `crear-cuenta` devuelve el borrador con `current_step`. Si el vínculo venció, se hace de nuevo y el borrador sigue intacto. Nada del alta se escribe en `localStorage`, `sessionStorage` ni cookies.

## Ingreso web y aviso de pago

El checkout confirma el pago por webhook y `SignUpInstitutionalEfector` crea el espacio y activa la licencia, pero hoy no avisa a nadie. Para el alta hecha en la web eso es un callejón sin salida: la persona no tiene app y el acceso web se aprueba desde la app.

**Ingreso**: el QR de la web apunta a una única URL https registrada como Universal Link / App Link. Todos los caminos son gratis; Didit se paga una sola vez, en el alta.

| Nivel | Camino | Prueba que aporta | Costo |
|-------|--------|-------------------|-------|
| 1 | La URL abre la app; huella local desbloquea el token guardado y la app aprueba el desafío | Dispositivo enrolado + biometría local | Cero |
| 2 | Passkey **del teléfono** (el navegador muestra un QR y el usuario confirma en su equipo): nada queda en la computadora | Credencial del dispositivo personal | Cero |
| 3 | Recuperación asistida (teléfono nuevo, sin app ni passkey): aprobación de superadmin, código de un solo uso y passkey nueva | Revisión humana + código | Cero |

No existe API para saber si la app está instalada: la decisión la toma el sistema operativo con el enlace universal, y el respaldo son botones explícitos. El desbloqueo biométrico local no aporta identidad: sólo libera un token de un dispositivo ya enrolado y debe morir con la revocación de la sesión.

**Passkeys en computadoras compartidas**: `webauthn_credential` guarda una fila por credencial (con `name`, `last_used_at` y `revoked_at`), así que una persona puede tener varias y revocar una sin tocar las demás. El pedido de autenticación va sin lista de credenciales, de modo que el autenticador muestra **todas las passkeys del equipo para el sitio** y el usuario elige la suya. La advertencia es la cuenta de sistema compartida: si toda la clínica usa el mismo usuario de Windows, la passkey de plataforma queda en ese perfil y el PIN es de todos; por eso en equipos compartidos el camino recomendado es la passkey del teléfono.

**Duración de sesión**: corta y sin "recordar este navegador". Al dejar de costar el ingreso, no hay nada que amortizar y un equipo compartido no conserva acceso.

**Aviso**: el e-mail confirmado es el canal obligatorio del aviso de pago; el push de la app es complementario y sólo si el dispositivo está registrado. El aviso es una notificación del dominio de facturación: copy en catálogo YAML con su loader y disparo en la política, idempotente por pago.

**Costos**: con Didit fuera del login, el costo por ingreso tiende a cero y el único gasto de identidad es el alta (0,33 USD por KYC, con 500 sesiones gratis por mes según [costos-didit.md](../../negocio/costos/costos-didit.md)). Mantener Didit como camino de login costaría 0,10 USD por ingreso: 1.000 ingresos web al mes son 70 USD, y 3.000 son 270 USD, escalando con ingresos y no con personas. Ese es el motivo por el que no es el camino por defecto.

## Por qué el código se muestra en la computadora

El QR puede ser fotografiado a distancia. Si el teléfono pudiera completar el alta con sólo escanear, un tercero podría iniciar el alta a nombre de otra persona con su propio teléfono y su propia identidad.

El código invierte la prueba: lo ve quien está frente a la computadora que inició el vínculo. Quien escanea un QR ajeno no ve ese código, no puede autorizar su teléfono y el vínculo queda en `claimed` hasta expirar.

## Estados del vínculo

| Estado | Significado | Quién lo provoca |
|--------|-------------|------------------|
| `pending` | Vínculo creado y QR emitido; ningún teléfono lo abrió | Navegador (`vinculo-iniciar`) |
| `claimed` | El teléfono abrió la URL; el código ya se muestra en la computadora | Teléfono (`GET /alta/vinculo/<token>`) |
| `authorized` | El código se validó en el teléfono | Teléfono (`vinculo-codigo`) |
| `verification_started` | Se creó la sesión hosted de Didit para ese vínculo | Teléfono (`vinculo-didit`) |
| `verified` | Didit aprobado y confirmado por el servidor; el `verification_id` quedó asociado | Teléfono (`vinculo-retorno`) |
| `account_created` | Persona + User creados o reutilizados y solicitud de incorporación registrada | Navegador (`completar`) |
| `cancelled` | El usuario cerró el alta en la computadora | Navegador (`vinculo-cancelar`) |
| `expired` | Venció el TTL de la etapa en curso | Lectura de estado |
| `failed` | Intentos de código agotados o verificación rechazada | Servicio |

Un estado sólo avanza: no se puede volver de `authorized` a `claimed` ni de `verified` a `verification_started`. Cada transición es un `UPDATE` condicionado por el estado anterior; si la fila no cambia, la operación falla con un error explícito.

## Tiempos y límites

| Regla | Valor propuesto |
|-------|-----------------|
| TTL para que el teléfono abra la URL | 180 s |
| TTL para completar verificación y alta después del reclamo | 600 s |
| Tope absoluto del vínculo | 15 min |
| Intentos de código | 5; agotados ⇒ `failed` |
| Consulta de estado desde la computadora | cada 2 s, con corte al expirar |
| Altas iniciadas por IP | el límite del guard de abuso del alta |

Los valores son knobs en `params` (`webSignupPairing`), no constantes dispersas.

## Seguridad

- **Token**: 32 caracteres aleatorios en hexadecimal, guardado como hash. Se consume en el reclamo: un segundo teléfono que abre la misma URL recibe "vínculo ya utilizado".
- **Binding**: el vínculo guarda el hash de la sesión Yii de la computadora y, tras el reclamo, el hash de la sesión del teléfono. Todo pedido del teléfono exige coincidencia de sesión, no sólo el `id` del vínculo.
- **Código**: se genera en el `start`, se devuelve una sola vez al navegador que lo pidió y se guarda únicamente hasheado. La interfaz lo revela cuando el estado es `claimed`.
- **CSRF**: las acciones JSON del alta exigen `X-CSRF-Token`, como el resto de las acciones JSON del login; el meta ya lo emite `loginLayout`.
- **Didit**: la sesión hosted se crea en el servidor con `vendor_data` = identificador del vínculo (sin datos de la persona) y `callback` a la ruta de retorno del teléfono. Al volver, el servidor consulta la decisión y descarta cualquier `status` que venga en la URL.
- **Datos personales**: no viajan al navegador de la computadora antes del alta (sólo nombre y documento resumidos después, si producto lo pide). La página del teléfono no muestra datos de la computadora.
- **Auditoría**: cada transición deja la fila con su timestamp; se registra en log de categoría propia el vínculo, el hash de IP y el resultado, nunca el token ni el código.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Un atacante escanea el QR y espera que alguien lea el código por teléfono | El código se muestra en la computadora del que inició el vínculo; el atacante no lo ve |
| El usuario abre la URL en el navegador equivocado o pierde la pestaña | El vínculo expira solo; la computadora ofrece reiniciar sin perder el centro elegido |
| Didit devuelve `pending` por demora de webhook | El teléfono reintenta la consulta de decisión antes de declarar `failed`; `pending` no es rechazo |
| Persona ya existente con otra cuenta | `CreateIdentityAccount` reutiliza Persona y User; el alta informa "cuenta ya existente" y registra la solicitud |
| Alta abandonada a mitad de camino | No crea nada: la cuenta se crea recién en `completar`; el borrador comercial queda en el servidor y se retoma con el paso exacto |
| Alta paga sin e-mail confirmado | El checkout queda bloqueado con un mensaje que explica confirmar o cambiar el e-mail; nunca se cobra sin canal de aviso |
| Pago confirmado sin aviso | El aviso es idempotente por pago y se reintenta; si el e-mail no estaba confirmado al pagar, queda pendiente y sale al confirmarse |
| Costo de soporte por bloqueo de cámara o permisos | La página del teléfono explica y ofrece reintentar la verificación sin rehacer el vínculo |

## Alternativas descartadas

- **Subir fotos del DNI desde la computadora**: exige cámara o escaneo en el escritorio y no agrega seguridad; es justamente lo que hizo retirar el alta web anterior.
- **QR que autoriza directo, sin código**: elimina la prueba de proximidad entre las dos pantallas y permite que un QR fotografiado complete un alta con el teléfono del atacante.
- **Crear la cuenta y la sesión web en un solo paso**: contradice la decisión vigente de acceso web por aprobación desde la app y obliga a relajar `StaffMobileAccessService`.
- **Reutilizar `auth_challenge` para el vínculo**: esa tabla es de autenticación (passkeys y login QR) y no modela efector, verificación ni cuenta; mezclar ambas responsabilidades complica los dos flujos.
- **Implementar el alta en la app con deep link**: obliga a actualizar las apps y a resolver el retorno del SDK nativo; el navegador del teléfono ya resuelve el caso con Didit hosted.
- **Guardar el borrador en el navegador**: duplica la fuente de verdad, se pierde al cambiar de equipo y no puede revalidar la identidad; el borrador ya vive en `commercial_signup_draft`.
- **Pedir e-mail como requisito de toda cuenta**: el alta de identidad no necesita e-mail y su confirmación no es un factor de autenticación; se exige sólo donde hay un aviso que entregar.
- **Obligar a instalar la app para entrar a la web**: con passkey del teléfono el acceso no depende de la app y no hay que forzar una instalación a un titular administrativo.

## Alternativas evaluadas para el login web

| Alternativa | Costo | Veredicto |
|-------------|-------|-----------|
| App con huella local (QR) | 0 | **Sí** — camino principal; requiere completar la ruta y el handler del enlace |
| Passkey del teléfono | 0 | **Sí, en v1** — resuelve la computadora compartida y no deja credencial en el equipo |
| Recuperación asistida | 0 | **Sí** — ya existe; es el camino para quien no tiene app ni passkey |
| Verificación de identidad (Didit) en cada ingreso | 0,10 USD por ingreso | No: escala con ingresos y en equipos compartidos las sesiones son cortas, así que no hay forma de amortizarlo |
| TOTP con app autenticadora | 0 | No por ahora: funciona, pero suma soporte (enrolamiento y códigos de recuperación) sin resolver nada que la passkey no resuelva mejor |
| Magic link por e-mail | ~0 | No: contradice la decisión de que el e-mail no es autenticación; sólo serviría como bootstrap de enrolamiento |
| Código por WhatsApp | Bajo por conversación | No: costo variable y riesgo de SIM swap |
| Usuario y contraseña | 0 | No: es el mecanismo que se retiró por soporte y phishing |

## Dónde vive cada pieza

| Pieza | Ubicación |
|-------|-----------|
| Estados y transiciones del vínculo | `common/components/Domain/Organization/Efector/Application/Service/SignupPairingService` |
| Sesión hosted de Didit | `common/components/Domain/Person/Identity/Application/UseCase/` (caso de uso que envuelve el cliente Didit) |
| Render de QR a SVG | `common/components/Shared/Infrastructure/` (extraído de `WebAuthController`, segundo consumidor) |
| Página y acciones web | `web/frontend/controllers/AltaController` + `frontend/views/alta/` + `frontend/web/js/alta-vinculo.js` |
| Rutas | reglas específicas en `web/frontend/config/main.php`, antes de los patrones amplios |
| Tabla | `web/common/migrations/mYYMMDD_HHMMSS_signup_pairing.php` |
| Borrador comercial | `commercial_signup_draft` + `CommercialSignupDraftService` (ya existen; no se duplican) |
| Copy del aviso de pago | catálogo YAML del dominio de facturación + su loader, con el disparo en la política PHP |
| Ingreso web | desafío y aprobación existentes en `Platform/Core/Auth/` + `PasskeyAuthenticationService` + URL universal/App Link + handler en las apps + página intermedia del teléfono |

## Pendientes antes de habilitar producción

- Job o comando de limpieza de vínculos vencidos (si el volumen lo justifica).
- Revisar con Didit que la URL de retorno del teléfono esté en la allowlist por entorno.
- Definir si el resultado muestra nombre y documento en la computadora o sólo el estado.
- Registrar la URL de ingreso como Universal Link / App Link en iOS y Android y verificar que el QR abra la app en un dispositivo real, por entorno.
- Medir el costo de identidad por alta y confirmar que el login no dispara sesiones de Didit (meta: cero por ingreso).
- Verificar el envío de e-mails en producción (SMTP configurado) antes de bloquear el checkout por e-mail no confirmado.
- Revisar los límites de reenvío de confirmación para que el bloqueo del checkout no se vuelva un bucle de soporte.
