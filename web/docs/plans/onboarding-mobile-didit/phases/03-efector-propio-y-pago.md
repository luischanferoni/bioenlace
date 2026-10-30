# Fase 3 — Crear efector propio (clínica/consultorio) + pago

## Objetivo

Permitir que una identidad verificada cree su propio efector (clínica/centro o consultorio) pagando, desacoplado de la creación de la identidad.

## Entregables

- Endpoint `registrar-efector` extendido: acepta `verification_id` (Didit) y crea efector + billing + entitlements activos sobre la identidad verificada (sin re-tipear datos ni contraseña).
- Consultorio: validación REFEPS/SISA del profesional + plan ambulatorio fijo.
- Clínica/centro privado: plan AMB/EMER/IMP + pago.
- Clínica/centro público: ministerio + cobertura → `PENDING` (sin pago).
- Ubicación estructurada (`id_localidad`) y, si aplica, coordenadas del pin.

## Decisiones a cerrar

- Endpoint de estimación de plan (reutilizar `pricing-core` server-side) o portar el cálculo a la app.
- Almacenamiento de coordenadas por efector (hoy las coordenadas viven en `geo_localidades`).

## Criterios de aceptación

- Crear cuenta (identidad) y pagar el efector son pasos separados.
- Un consultorio se activa solo tras pago aprobado (simulado hoy).
- La ubicación queda estructurada, sin localidad por defecto.
