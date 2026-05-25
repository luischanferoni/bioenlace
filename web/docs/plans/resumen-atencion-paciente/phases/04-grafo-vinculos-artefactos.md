# Fase 4 — Grafo de vínculos

## Objetivo

Navegar entre atención, receta, pedidos y resultados (datos parciales + enlaces).

## Checklist

- [x] Extender DTO resumen: `prescriptions[]`, `orders[]`, `laboratoryReports[]`
- [x] CTAs Flutter: ver receta / informe lab vía `UiJsonScreen` (`detailApiRoute`)
- [x] Laboratorio: `ver-informe-como-paciente` con `related_encounter_*`
- [ ] Derivaciones: enlazar `parent` / encounter hijo `finished` o turno futuro (opcional / siguiente iteración)
- [x] Pedidos sin resultado: `resultStatus` pending vs available
- [x] Reutilizar endpoints receta/lab existentes

## Modelo de enlaces (UI)

```text
Resumen atención
  → [Ver receta PDF]
  → [Estudios pedidos] → (cuando hay) [Ver resultado]
  → [Seguimiento / derivación] → otra atención o turno
```

## Criterio de cierre

Desde resumen se abre receta; desde informe lab se vuelve a la atención que originó el pedido.
