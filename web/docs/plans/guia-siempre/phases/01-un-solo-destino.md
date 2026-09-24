# Fase 1 — Un solo destino

## Objetivo

Un match `clara` y uno `incompleta` llaman a la guía. El flow no se abre en esa respuesta. El botón que ya ofrece la guía, al tocarlo, sigue abriendo el flow.

## Checklist

- [ ] El handler de match claro deja de armar el botón solo y entra al mismo camino que incompletas
- [ ] Artículo, plantilla, saludo y tema ajeno al HIS no pasan por la guía
- [ ] El atajo del inicio sigue abriendo el flow al tocarlo
- [ ] Tests de routing: un intent que hoy es claro produce el camino de la guía, no un sobre de flow

## Qué no entra

Necesidades con estado, persistencia, ni tags por estado.
