# Fase 01 — Regla y medición

## Objetivo

Definir qué es "mensaje de respuesta" y ajustar el contador del auditor para que lo mida
por separado de las etiquetas. Sin esto, el resto del plan no tiene criterio de cierre.

## Tamaño

**Chico (medio día).** Sólo documentación y un ajuste al auditor.

## Tareas

- [ ] Escribir la regla: el texto de un mensaje de respuesta viene del payload (`message`);
      el cliente sólo lo muestra y aporta un **fallback** para cuando no hay payload.
- [ ] Clasificar los literales actuales en 4 tipos (respuesta / validación de UX / etiqueta /
      técnico) y registrar el conteo de cada uno en el baseline.
- [ ] Ajustar `web/tools/audit-thin-clients.php` para que los contadores de clientes
      distingan mensaje de etiqueta (hoy suman todo).
- [ ] Re-basar `thin-clients-baseline.json` con los números separados.
- [ ] Escribir la regla en `.cursor/rules/` para que el agente la aplique al escribir código
      nuevo (mensaje nuevo en cliente = error).

## Aceptación

- El reporte del auditor muestra mensajes y etiquetas en contadores distintos.
- Hay una regla escrita que un agente puede seguir sin interpretar.
- El número de mensajes a migrar es explícito y menor que el contador actual.

## Riesgos

- Clasificar 800 literales a mano no escala. Alcanza con clasificar por archivo y por patrón
  (los `_snack(` y `'message' =>` son mensajes casi con certeza; los `Text('…')` con
  mayúscula inicial y sin verbo, etiquetas).
