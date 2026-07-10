# Alta de cuenta desde institucional

**Estado:** MVP implementado (pasarela simulada; ministerio asistido)  
**Objetivo:** self-service AdminEfector + pasarela simulada; solicitud asistida AdminMinisterio; sector público/privado y vínculo de pago.

## Alcance MVP

1. Wizard en sitio institucional → API pública → usuario + persona + efector + `billing_account` EFECTOR + entitlements + PES AdminEfector + pago simulado.
2. Solicitud ministerio (sin alta operativa) → cola en admin → aprobación crea cuenta MINISTERIO + rol `AdminMinisterio`.
3. Al alta de efector: sector PUBLICO|PRIVADO; si público, ministerio obligatorio (AFILIADO). Pago propio (POOL) por defecto.
4. AdminEfector autenticado: desvincular/asociar **pago** (POOL) respecto de una cuenta ministerio (con reglas).

## Fuera de MVP

- Pasarela real (Mercado Pago / Stripe).
- KYC Didit obligatorio en alta institucional.
- UI completa multi-efector para AdminMinisterio en frontend clínico.

## Docs estables al cerrar

- `producto/alta-cuenta-licencia.md`
- `decisions/onboarding-comercial-self-service.md`
- Actualizar matriz de precios (pasarela simulada).
