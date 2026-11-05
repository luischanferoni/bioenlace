# Fase 04 — JS web y cierre

## Objetivo

Cerrar el último desvío (JS legacy) y bajar los baselines a los números declarados en la
fase 01.

## Tamaño

**Chico (medio día).**

## Tareas

- [ ] `web/frontend/web/js/pacientes-listing.js`: reemplazar los 38 literales por el `message`
      del payload; fallback desde un texto compartido.
- [ ] Revisar que el JS use el cliente de API compartido (`BioenlaceApiClient`) y no un
      `fetch` propio, para que el manejo de error venga resuelto.
- [ ] Confirmar en `docs/negocio/costos/estrategias-reduccion/monitoreo.md` si hay métricas de
      mensajes que dependan de estos textos.
- [ ] Bajar `web_js_literal_ui_text` al número de etiquetas declarado.
- [ ] Cierre: volcar la regla a `decisions/`, borrar esta carpeta.

## Aceptación

- `web_js_literal_ui_text` = sólo etiquetas.
- `composer audit:thin-clients` sin contadores nuevos.
- Un cambio de texto en `api-errors.yaml` se refleja en la web legacy sin tocar JS.

## Riesgos

- Es el único archivo JS con este patrón; el riesgo de regresión es bajo, pero está en la
  pantalla de listado de pacientes (uso intensivo del staff). Probar el flujo completo.
