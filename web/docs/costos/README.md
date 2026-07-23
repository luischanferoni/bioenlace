# Costos IA e infraestructura

Estimación y estrategias de ahorro en dos ejes: **infra propia (GPU)** y **APIs externas**.

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Objetivo del análisis de costos |
| [design.md](./design.md) | Por qué se separan ejes infra vs API |
| [infra-costos.md](./infra-costos.md) | Costos infra (GPU) |
| [infra-estrategias.md](./infra-estrategias.md) | Estrategias infra |
| [costos-api.md](./costos-api.md) | Costos APIs (Gemini Flash Lite, comparativa DeepSeek, Groq STT, Vision, WhatsApp reactivo §7; catálogo de contextos) |
| [costos-didit.md](./costos-didit.md) | Didit — KYC y biometría remota (proyección por altas y reingresos) |
| [analisis-videollamada-self-host.md](./analisis-videollamada-self-host.md) | Self-host; Track Egress muxing 1/4/12; 14 d → Deep Archive; COGS video **1,75** @ **40 %** |
| [resumen-costos-bioenlace.md](./resumen-costos-bioenlace.md) | Resumen de costos APIs (5.000 prof., sin jerga técnica) |
| [estrategias-reduccion/](./estrategias-reduccion/README.md) | Palancas de ahorro (context caching, STT, caché app, …) |
| [impuestos-argentina.md](./impuestos-argentina.md) | IVA, IIBB, ganancias — referencia AR (pricing y costo efectivo) |
| [pruebas-costos-ia.md](./pruebas-costos-ia.md) | Pruebas simuladas |

## Relacionado

- [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — de COGS a precio lista (margen + add-ons)
- [producto/asistente-y-chat.md](../producto/asistente-y-chat.md) — asistente + WhatsApp (solo iniciado por paciente)
- [producto/turnos.md](../producto/turnos.md) — notificaciones (push; WhatsApp utility no habilitado)
- [producto/apps-paciente-personalsalud.md](../producto/apps-paciente-personalsalud.md)
