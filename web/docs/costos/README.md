# Costos – Índice y enfoque

Esta carpeta organiza los costos en **dos ejes** para poder ahorrar al máximo:

- **[Infra](infra/costos.md)** – Coste cuando la IA y la carga corren en **nuestra infra** (GPU: RunPod, AWS, GCP).
- **[API](api/costos.md)** – Coste cuando usamos **APIs externas** (Vertex/Gemini, STT, Vision, videollamadas) y cómo reducirlo.

Cada eje tiene su documento de **costos reales** y su documento de **estrategias de reducción**.

---

## Estructura

| Documento | Contenido |
|-----------|-----------|
| [infra/costos.md](infra/costos.md) | Planes GPU, $/consulta, $/médico/mes; cargas adicionales de IA en nuestra infra (pre-consulta, onboarding, **comunicación previa al turno**). |
| [infra/estrategias.md](infra/estrategias.md) | Cómo reducir coste de infra: elección de plan (Spot/Preemptible), optimizaciones de código, economías de escala. |
| [api/costos.md](api/costos.md) | Precios de referencia (Vertex, STT, Vision, video); coste por capacidad cuando se usa API; **comunicación previa al turno** incluida. |
| [api/estrategias.md](api/estrategias.md) | Cómo reducir coste vía API: modelo/proveedor, caché, tokens, uso condicional; estrategias por capacidad (pre-consulta, pre-turno, onboarding, medios, video). |

---

## Comunicación previa al turno (nueva partida)

El chat/bot debe **guiar al paciente en la comunicación previa a sacar el turno**. Es decir, hay una **conversación previa al posible turno** (pre-turno) que puede terminar en turno o no, y que **no estaba contemplada** en los costos anteriores.

- Se documenta y presupuesta en [infra/costos.md](infra/costos.md) (si la IA corre en nuestra GPU) y en [api/costos.md](api/costos.md) (si la IA corre vía API).
- Las estrategias para reducir su coste están en [api/estrategias.md](api/estrategias.md) (caché, reglas, menos % que llama a IA), y el efecto sobre carga de infra en [infra/estrategias.md](infra/estrategias.md).

---

## Referencias

- [CAPACIDADES_PACIENTE_MEDICO.md](../CAPACIDADES_PACIENTE_MEDICO.md) – Descripción de las capacidades.
- [OPTIMIZACIONES_CODIGO.md](../OPTIMIZACIONES_CODIGO.md) – Optimizaciones desde el código.
