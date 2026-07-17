# Costos — Visión general

## Qué es

Documentación de **costos estimados** de capacidades de IA (conversación con el paciente, motivos de consulta, onboarding, captura del médico) y de la infraestructura que las ejecuta.

## Objetivo

Comparar escenarios **GPU propia** vs **API managed** y registrar estrategias para reducir gasto sin perder capacidades de producto.

**COGS** (*Cost of Goods Sold*): costo variable directo de APIs por uso (IA, STT, videollamada); definición completa en [costos-api.md § COGS](./costos-api.md#cogs-abreviatura).

## Ejes

| Eje | Pregunta |
|-----|----------|
| Infra | ¿Cuánto cuesta correr modelos en nuestra GPU? |
| API | ¿Cuánto cuesta Vertex/STT/Vision + videollamada? IA/medios **~$3,5–3,7/prof**; con video §6 **~$8–9/prof** ([costos-api.md](./costos-api.md)) |
| WhatsApp (§7) | ¿Costo Meta del asistente reactivo? **~$0** (utility **no habilitada**) — [costos-api §7](./costos-api.md#7-whatsapp-cloud-api-paciente) |
| Identidad (Didit) | ¿Cuánto cuesta KYC y reingreso biométrico? → [costos-didit.md](./costos-didit.md) (500 gratis/mes; luego ~0,33 KYC / ~0,10 reingreso) |
| Fiscal (AR) | ¿Qué sumar de IVA, IIBB y ganancias al costo y al precio? → [impuestos-argentina.md](./impuestos-argentina.md) |

Detalle en los archivos listados en [README.md](./README.md).
