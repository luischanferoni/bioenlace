# Estrategias de reducción de costos – Infraestructura

Este documento detalla cómo **reducir el costo real** de la infra (GPU en RunPod, AWS, GCP). El costo real de referencia está en [infra/costos.md](costos.md). Las reducciones son **porcentajes sobre ese costo real**.

---

## Resumen

| Palanca | Reducción estimada (% del costo real de infra) |
|---------|--------------------------------------------------|
| Optimizaciones de código (menos carga por consulta) | **40–60%** |
| Modelos más pequeños o específicos por tarea | **20–50%** (coste de inferencia) |
| Elegir plan más barato (Spot/Preemptible vs RunPod) | **43–88%** |
| Economías de escala (más consultas por misma instancia) | Menor $/consulta (no % del total) |

---

## 1. Aplicar optimizaciones de código

- **Qué hace**: Caché, uso condicional de IA, procesamiento selectivo, etc., reducen el número de llamadas y la carga por consulta, lo que permite **menos instancias** o instancias más baratas.
- **Dónde**: Parámetros en `params.php` (`usar_cpu_tareas_simples`, cachés, etc.); flujo híbrido en corrección de texto; ver [api/estrategias.md](../api/estrategias.md) para tácticas que reducen llamadas a IA (aplicables también cuando la IA corre en nuestra infra).
- **Reducción estimada**: **40–60%** del costo de infra (referencia [infra/costos.md](costos.md)). Ejemplo: RunPod $8.36/médico/mes → con optimizaciones puede bajar a $3–5/médico/mes.

---

## 2. Elegir el plan de hosting adecuado

- **Qué hace**: Comparar RunPod (precio fijo, estable), AWS Reserved (descuento por compromiso), **AWS Spot** y **GCP Preemptible** (más baratos, con riesgo de interrupciones).
- **Cómo**: Producción crítica → RunPod o AWS Reserved. Pruebas o cargas tolerantes a cortes → Spot/Preemptible con fallback o reintentos.
- **Reducción estimada**: **43–88%** del costo de hosting si se pasa de RunPod ($8.36/médico/mes) a Spot/Preemptible ($1.40–4.56), a costa de estabilidad.

---

## 3. Economías de escala

- **Qué hace**: A mayor volumen de consultas por médico (o por instancia), el costo **por consulta** baja cuando el costo mensual del plan es fijo (RunPod).
- **Cómo**: Agrupar médicos en la misma infra; dimensionar instancias para un uso medio-alto sin sobredimensionar.
- **Efecto**: No es % de reducción del costo total mensual del plan, sino **menor costo por consulta** (referencia [infra/costos.md](costos.md), escenarios por volumen).

---

## 4. Usar modelos más pequeños o específicos por tarea

- **Qué hace**: En nuestra GPU el costo depende del tiempo de inferencia. Modelos con menos parámetros (p. ej. 7B frente a 13B/70B) o modelos acotados por tarea (corrección, clasificación de intents, resúmenes cortos) consumen menos tiempo de GPU por llamada, lo que reduce el costo por consulta.
- **Cómo**: Sustituir el modelo grande por uno más pequeño donde la calidad lo permita; usar encoders o modelos de 1–3B para tareas concretas (corrección, clasificación) y reservar el modelo grande solo para chat complejo o análisis. Parámetros de modelo en `params.php` (`hf_model_*`, etc.).
- **Reducción estimada**: **20–50%** del costo de inferencia en conjunto; si la mayoría de las llamadas pasan a modelos pequeños o específicos, hasta **40–60%**. Se suma a las optimizaciones de código (caché, uso condicional).

---

## Reducir carga de IA en nuestra infra

Las estrategias que **reducen llamadas a IA** (caché, reglas antes de IA, menos % de mensajes que llaman al modelo) también reducen la carga sobre nuestra GPU. Esas tácticas se detallan en [api/estrategias.md](../api/estrategias.md) (pre-consulta, pre-turno, onboarding). Aplicarlas permite bajar el número de llamadas de las capacidades “comunicación previa al turno”, “pre-consulta” y “onboarding” y por tanto el costo adicional por médico en [infra/costos.md](costos.md).

---

## Referencias

- [infra/costos.md](costos.md) – Costos reales por plan y cargas adicionales.
- [api/estrategias.md](../api/estrategias.md) – Estrategias que reducen uso de IA (y por tanto carga en nuestra infra).
