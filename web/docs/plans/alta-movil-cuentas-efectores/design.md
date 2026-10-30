# Design — Alta móvil de cuentas y efectores

## Decisiones cerradas

| Tema | Decisión |
|------|----------|
| Canal de alta | Las tres opciones se crean desde Personal de Salud |
| Verificación civil | Documento + selfie desde la app; sus atributos verificados se reutilizan |
| E-mail | No requerido ni verificado para crear una cuenta; nunca es autenticación ni recuperación suficiente |
| Cuenta profesional | Puede existir sin centro; no tiene acceso clínico hasta que AdminEfector la incorpore |
| Incorporación a centro | Solo la inicia AdminEfector buscando una persona existente; no existe “sumarme a un centro” self-service |
| REFEPS | No para identidad ni para crear/administrar centro; sí al asignar rol clínico y antes de comprar consultorio propio |
| Consultorio propio | Alta paga de una persona; la misma cuenta separa la responsabilidad administrativa de la habilitación clínica |
| Clínica/centro | Alta paga; asigna AdminEfector, que es web-only en esta etapa |
| Pago | Mostrar precio, recurrencia, impuestos, renovación y cancelación antes de autorizar; activar licencia solo tras confirmación |
| Persistencia | Guardar el alta y cada intento de pago con idempotencia para reanudar fallos sin duplicados |
| Web | Passkeys como acceso principal; aprobación QR desde app para personas con rol habilitado en ambos canales |
| Recuperación | App: nueva verificación de identidad. Web-only: recuperación asistida con verificación de identidad, revocación de credenciales y sesiones |
| Acceso móvil | Allowlist explícita de roles aplicada en servidor; AdminEfector excluido por ahora; Administrativo y futuros roles web-only por defecto |
| Compatibilidad | No conservar flujos legacy de contraseña, código, alta comercial web ni autorización móvil implícita; migrar cuentas y relaciones |

## Estados de identidad y habilitación

La identidad verificada no implica matrícula, asignación, licencia ni acceso.

1. `identity_verified`: Persona identificada y cuenta creada.
2. `professional_verification_pending`: REFEPS pendiente o requiere revisión manual.
3. `professional_verified`: habilitación compatible confirmada.
4. `assigned`: AdminEfector creó la asignación al efector.
5. `license_pending_payment` / `license_active`: checkout pendiente o pago confirmado.

Solo una asignación activa con rol clínico válido y licencia habilitada permite operar. Fallo de REFEPS y falta de respuesta se distinguen; un error temporal no equivale a rechazo.

## Seguridad de checkout

- Nunca enviar ni persistir PAN/CVV en Bioenlace; usar checkout alojado/tokenización del proveedor.
- El servidor calcula precios y condiciones desde metadata, no acepta total ni rol de cliente como autoridad.
- Un intento tiene clave idempotente y estados monotónicos; webhooks se autentican y deduplican.
- Un pago aprobado se concilia antes de crear/activar entitlement; los reintentos no vuelven a cobrar.
- Los fallos dejan el alta recuperable y sin permisos pagos.

## QR de acceso web

El QR de login es distinto del QR para asociar un dispositivo al alta. Autoriza un desafío específico del navegador, de un solo uso, corto, con origin/audience fijados y confirmación explícita desde la app. No expone tokens de sesión en el QR. Se registra actor, dispositivo, sesión y resultado.

## Transición sin legacy

No se conserva un endpoint o pantalla antigua como fallback de autenticación. La migración preserva los objetos de dominio y obliga a establecer una credencial passkey segura al próximo acceso web. Se define un periodo de enrolamiento con bloqueo de rutas protegidas, no una vuelta a contraseña.

## Decisiones técnicas por cerrar antes del checkout

- Proveedor de pago y soporte de suscripción/renovación.
- Moneda, IVA/impuestos, factura y cancelación/prorrateo.
- Alcance y cobertura real de REFEPS por profesión/jurisdicción; protocolo de revisión manual.
- Atributos mínimos que se conservan de la verificación y retención/auditoría.
- Vía asistida para AdminEfector sin dispositivo compatible con passkey y para recuperación web-only.
