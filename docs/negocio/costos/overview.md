# Costos — Visión general

## Qué es

Documentación de **costos estimados** de capacidades de IA (conversación con el paciente, motivos de consulta, onboarding, captura del médico) y de la infraestructura que las ejecuta.

## Objetivo

Comparar escenarios **GPU propia** vs **API managed** y registrar estrategias para reducir gasto sin perder capacidades de producto.

**COGS** (*Cost of Goods Sold*): costo variable directo de APIs por uso (IA, STT, videollamada); definición completa en [costos-api.md § COGS](costos-api.md#cogs-abreviatura).

## Ejes

| Eje | Pregunta |
|-----|----------|
| Infra | ¿Cuánto cuesta correr modelos en nuestra GPU? |
| API | ¿Cuánto cuesta Vertex/STT/Vision + videollamada? IA + STT planificación **~$3,95–4,31/prof** (con/sin context caching; motivos sin audio); con video §6 @ 40 % **~$6,1–6,5/prof** ([costos-api.md](costos-api.md)) |
| WhatsApp (§7) | ¿Costo Meta del canal? Reactivo **ya no es $0** (service se cobra desde 1/10/2026 después de 1.000/mes); proactivo es add-on opcional → [costos-whatsapp.md](costos-whatsapp.md) |
| Identidad (Didit) | ¿Cuánto cuesta KYC y reingreso biométrico? → [costos-didit.md](costos-didit.md) (500 gratis/mes; luego ~0,33 KYC / ~0,10 reingreso) |
| Fiscal (AR) | ¿Qué sumar de IVA, IIBB y ganancias al costo y al precio? → [impuestos-argentina.md](impuestos-argentina.md) |
| Centro de datos | ¿Conviene comprar el hardware? → [analisis-centro-datos-propio.md](analisis-centro-datos-propio.md) (a 5.000 prof. **no**: +43–67 %; GPU solo para STT; LLM 14B; reabrir a ~25.000) |

Detalle en los archivos listados en [README.md](README.md).
