# Fase 4 — Grafo de vínculos

## Objetivo

Navegar entre atención, receta, pedidos y resultados (datos parciales + enlaces).

## Checklist

- [ ] Extender DTO resumen: `prescriptions[]`, `orders[]` (service_request por categoría)
- [ ] CTAs en UI JSON / Flutter: ver receta, ver pedido, abrir care plan si aplica
- [ ] Laboratorio: en `ver-informe-como-paciente`, bloque `relatedEncounter` (id, fecha, profesional, teaser narrative)
- [ ] Derivaciones: enlazar `parent` / encounter hijo `finished` o turno futuro
- [ ] Pedidos sin resultado aún: estado “pendiente” vs “con informe”
- [ ] No duplicar lógica lab/receta — reutilizar endpoints existentes con `encounter_id` en query

## Modelo de enlaces (UI)

```text
Resumen atención
  → [Ver receta PDF]
  → [Estudios pedidos] → (cuando hay) [Ver resultado]
  → [Seguimiento / derivación] → otra atención o turno
```

## Criterio de cierre

Desde resumen se abre receta; desde informe lab se vuelve a la atención que originó el pedido.
