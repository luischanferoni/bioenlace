# Alta de cuenta desde institucional

**Estado:** MVP implementado (pasarela simulada; ministerio asistido; perfil consultorio opción A)  
**Objetivo:** self-service AdminEfector (clínica o consultorio unipersonal) + pasarela simulada; solicitud asistida AdminMinisterio; sector público/privado y vínculo de pago; guía post-alta para PES clínico.

## Alcance MVP

1. Wizard en sitio institucional → API pública → usuario + persona + efector + `billing_account` EFECTOR + entitlements + PES AdminEfector + pago simulado.
2. Perfil `CONSULTORIO` (opción A): defaults de plan/tipología; respuesta con `next_steps` para autoasignarse a un servicio clínico (sin auto-crear PES clínico).
3. Solicitud ministerio (sin alta operativa) → cola en admin → aprobación crea cuenta MINISTERIO + rol `AdminMinisterio`.
4. Al alta de efector: sector PUBLICO|PRIVADO; si público, ministerio obligatorio (AFILIADO). Pago propio (POOL) por defecto.
5. AdminEfector autenticado: desvincular/asociar **pago** (POOL) respecto de una cuenta ministerio (con reglas).
6. App Personal de Salud: CTA al alta web `?perfil=consultorio` (sin registro in-app).

## Fuera de MVP

- Pasarela real (Mercado Pago / Stripe).
- KYC Didit obligatorio en alta institucional.
- UI completa multi-efector para AdminMinisterio en frontend clínico.
- Auto-provisioning de PES clínico / servicio por defecto.

## Docs estables al cerrar

- `producto/alta-cuenta-licencia.md`
- `decisions/onboarding-comercial-self-service.md`
- Actualizar matriz de precios (pasarela simulada).
