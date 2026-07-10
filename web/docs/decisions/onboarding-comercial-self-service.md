# Onboarding comercial self-service

## Contexto

Hacía falta un funnel desde el sitio institucional para vender licencia a clínicas y, por separado, incorporar ministerios sin abrir un agujero de gobernanza B2G.

## Decisión

1. **AdminEfector:** alta self-service (usuario + persona + efector + `billing_account` EFECTOR + entitlements + PES AdminEfector) con **pasarela simulada**.
2. **AdminMinisterio:** solo por **solicitud + aprobación humana** en admin; rol RBAC `AdminMinisterio` (incluido en `rolesEspeciales`).
3. Separar **afiliación** (`AFILIADO` a cuenta MINISTERIO) de **quién paga** (`POOL` en cuenta propia o ministerial).
4. Público exige ministerio; privado no. Cambios de pool ministerial requieren aprobación (no unilateral si consume cupo ajeno).

## Alternativas descartadas

- Self-service abierto de ministerio: riesgo de usurpación y blast radius alto.
- Mezclar sector y facturación en un solo campo: confunde efectores autárquicos.

## Consecuencias

- API pública bajo `/api/v1/licencia/*` (excepto acciones AdminEfector autenticadas).
- Tablas `billing_payment`, `billing_signup_request`; `billing_account.owner_user_id`.
- Pasarela real (MP/Stripe) reemplaza `SimulatedPaymentGateway` sin cambiar el alta.
