# Fase 7 — Desarmar alta web + hardening

## Objetivo

Retirar el alta web self-service y cerrar validaciones de seguridad.

## Entregables

- `institucional/alta.html`: reemplazar el formulario por "cómo crear tu cuenta" → descargar app; ministerio → contacto.
- Eliminar `institucional/js/signup.js` y los estilos `.signup-*` de `styles.css`.
- Actualizar navegación institucional ("Crear cuenta" → "Descargar app"/contacto).
- Teléfono E.164 opcional en backend.
- Geo-gating AR/UY en servidor.
- Rate limit + honeypot en endpoints públicos de alta.

## Decisiones a cerrar

- Página destino del CTA de ministerio (contacto).
- Proveedor de mapa en la app (Leaflet/OSM para MVP).

## Criterios de aceptación

- No queda formulario de alta funcional en el sitio institucional.
- Los endpoints públicos de alta no crean cuentas sin identidad verificada.
