# Onboarding comercial self-service

## Contexto

Hacía falta un funnel desde el sitio institucional para vender licencia a clínicas y, por separado, incorporar ministerios sin abrir un agujero de gobernanza B2G. También había que cubrir al **profesional con consultorio propio** sin inventar un modelo de licencia atada a persona sin efector.

## Decisión

1. **AdminEfector (clínica / efector):** alta self-service (usuario + persona + efector + `billing_account` EFECTOR + entitlements + PES AdminEfector) con **pasarela simulada**.
2. **Profesional independiente = opción A (consultorio unipersonal):** mismo flujo cuenta → efector → pool, con perfil `CONSULTORIO` (solo AMB, default `max_pes` = 1), **solo sector PRIVADO**. **No** alta pública self-service para profesional (si trabaja en un centro público, lo suma AdminEfector). **No** licencia sin efector ni PES clínico sin efector. Urgencia/internación van por el alta de clínica/centro.
3. **Plan clínica (`CLINICA`):** clases AMB / EMER / IMP opcionales (mínimo una); no se fuerza ambulatorio.
4. **Post-alta clínica:** no auto-crear PES clínico; se **guía** al usuario para asignarse a sí mismo en un servicio clínico (agenda/captura).
5. **AdminMinisterio:** solo por **solicitud + aprobación humana** en admin; rol RBAC `AdminMinisterio` (incluido en `rolesEspeciales`).
6. Separar **afiliación** (`AFILIADO` a cuenta MINISTERIO) de **quién paga** (`POOL` en cuenta propia o ministerial).
7. Público exige ministerio; privado no. Cambios de pool ministerial requieren aprobación (no unilateral si consume cupo ajeno). **Consultorio (`CONSULTORIO`) no ofrece sector público** en institucional ni en API.

## Alternativas descartadas

- Self-service abierto de ministerio: riesgo de usurpación y blast radius alto.
- Mezclar sector y facturación en un solo campo: confunde efectores autárquicos.
- Licencia atada a persona sin efector / PES sin efector: rompe el modelo de cupos y sesión operativa.
- Auto-crear PES clínico en el alta: oculta el paso de asignación a servicio y complica tipologías.

## Consecuencias

- API pública bajo `/api/v1/licencia/*` (excepto acciones AdminEfector autenticadas); payload `perfil` (`CLINICA`|`CONSULTORIO`) y `next_steps` en la respuesta.
- Institucional: tabs clínica / consultorio / ministerio; deep-link `alta.html?perfil=consultorio`.
- App Personal de Salud: CTA al alta web (no registro in-app).
- Tablas `billing_payment`, `billing_signup_request`; `billing_account.owner_user_id`.
- Pasarela real (MP/Stripe) reemplaza `SimulatedPaymentGateway` sin cambiar el alta.
