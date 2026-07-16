# Videollamadas

**COGS de planificación (matriz / calculador):** **USD 3,00 / prof / mes** — ver [costos-api.md §6](../costos-api.md#6-videollamadas-pacientemédico).

Supuesto de uso: 30 % de las consultas × 12 min × 2 participantes ≈ **1.440 participant-minutes / prof / mes**.

## Roadmap de proveedor

| Fase | Proveedor | Rol |
|------|-----------|-----|
| **Corto / mediano** | [Daily.co](https://www.daily.co/pricing/video-sdk/) pay-as-you-go | Integración rápida; 10.000 pax-min gratis/mes por cuenta; luego ~$0,004/pax-min |
| **Mediano / largo** | Self-host (LiveKit u equivalente) + TURN dedicado + grabación | Bajar costo real hacia ~0,6–1,5 USD/prof a escala 500–5.000; el COGS lista **3,00** se mantiene como techo planificado |

El COGS **3,00** ya incluye buffer para sala + TURN + grabación y ops mínimo (100 → 5.000+ profesionales con video). No bajar el número de metadata solo porque el gasto real sea menor a escala: eso aumenta margen.

## Palancas operativas

- Límite de duración (aviso 15–20 min): **10–25 %** sobre minutos reales.
- Calidad adaptable / fallback audio.
- Alertar cuando `(gasto_infra_video + ops) / N_video > 3,00` varios meses seguidos → revisar retención de grabación o capacidad.

El context caching de Gemini **no** afecta videollamadas.
