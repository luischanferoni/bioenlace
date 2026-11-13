# Costos IA e infraestructura

Estimación y estrategias de ahorro en dos ejes: **infra propia (GPU)** y **APIs externas**. La pregunta por un **centro de datos propio** es una decisión aparte —capex, custodia física y escala— y se analiza en [analisis-centro-datos-propio.md](analisis-centro-datos-propio.md).

| Documento | Contenido |
|-----------|-----------|
| [overview.md](overview.md) | Objetivo del análisis de costos |
| [design.md](design.md) | Criterio de diseño: por qué dos ejes (infra vs API) y dónde vive cada tema |
| [infra-costos.md](infra-costos.md) | Costos infra (GPU) |
| [infra-estrategias.md](infra-estrategias.md) | Estrategias infra |
| [costos-api.md](costos-api.md) | Costos APIs (Gemini Flash Lite, comparativa DeepSeek, Groq STT, WhatsApp reactivo §7; catálogo de contextos). Análisis de imágenes: no ofrecido |
| [costos-didit.md](costos-didit.md) | Didit — KYC y biometría remota (proyección por altas y reingresos) |
| [costos-whatsapp.md](costos-whatsapp.md) | Add-on WhatsApp — mensajes proactivos, step-up Didit y política de datos sensibles |
| [analisis-videollamada-self-host.md](analisis-videollamada-self-host.md) | Self-host; Track Egress muxing 1/4/12; 14 d → Deep Archive; COGS video **2,19** @ **40 %** |
| [analisis-centro-datos-propio.md](analisis-centro-datos-propio.md) | Hardware comprado a 5.000 prof. con **LLM 14B**: GPU (IA) vs medios, capex, +43–67 % vs hoy; solo se justifica comprar GPU para STT |
| [resumen-costos-bioenlace.md](resumen-costos-bioenlace.md) | Resumen de costos APIs (5.000 prof., sin jerga técnica) |
| [estrategias-reduccion/](estrategias-reduccion/README.md) | Palancas de ahorro (context caching, STT, caché app, …) |
| [impuestos-argentina.md](impuestos-argentina.md) | IVA, IIBB, ganancias — referencia AR (pricing y costo efectivo) |
| [pruebas-costos-ia.md](pruebas-costos-ia.md) | Pruebas simuladas |

## Relacionado

- [docs/README.md § Fuente única](../../README.md#fuente-única-y-jerarquía-de-documentos) — qué documento es canónico por dominio
- [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — de COGS a precio lista (margen + add-ons)
- [producto/asistente-y-chat.md](../../producto/asistente-y-chat.md) — asistente + WhatsApp (solo iniciado por paciente)
- [costos-whatsapp.md](costos-whatsapp.md) — análisis del add-on opcional de WhatsApp proactivo
- [producto/turnos.md](../../producto/turnos.md) — notificaciones (push; WhatsApp utility no habilitado)
- [producto/apps-paciente-personalsalud.md](../../producto/apps-paciente-personalsalud.md)
