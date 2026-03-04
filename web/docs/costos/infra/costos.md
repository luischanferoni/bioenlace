# Costos – Infraestructura (nuestra GPU)

Este documento refleja el **costo real** de la infraestructura cuando la IA corre en **nuestra GPU** (RunPod RTX 3090 recomendado, GCP T4 Preemptible más barato), **sin aplicar** estrategias de reducción. Las reducciones posibles se documentan en [infra/estrategias.md](estrategias.md). **Por costo por consulta, nuestra GPU no resulta más barata que la API de OpenAI** (GPT-4o mini); la elección de infra propia se justifica por privacidad, cumplimiento normativo y control del modelo (ver comparación más abajo).

## Supuestos base

- **Consultas por médico**: 20/día = 400/mes (20 × 20 días).
- **Consulta típica**: 600–1.500 tokens totales (input + output). A mayor cantidad de tokens, mayor costo por consulta (más tiempo de GPU por inferencia).
- **Costo real**: se indica por plan y por ítem en las tablas siguientes. Las cifras asumen un **modelo de tamaño estándar**; con **modelos pequeños** (7B u equivalentes) el costo por consulta baja (ver sección [Modelos pequeños](#modelos-pequeños-7b-u-equivalentes-en-nuestra-gpu)).

---

## Planes de hosting GPU

Solo se comparan **dos opciones**: la **recomendada** (estable, precio fijo) y la **más barata** (preemptible, posible interrupción).

### Plan 1: RunPod RTX 3090 (Recomendado / GPU estándar)

- **Costo**: $8.36/instancia/mes
- **Referencia**: 400 consultas/instancia/mes (modelo estándar, consulta 1.500 tokens).
- **Costo por consulta** (a 400 consultas/mes): $8.36 ÷ 400 ≈ **$0.021/consulta**

**Ventajas**: Precio fijo, sin interrupciones, fácil de configurar.  
**Desventajas**: Escalado manual.

---

### Plan 2: GCP T4 (Preemptible) – Más barato / GPU mínima

- **Costo**: $1.40–3.78/instancia/mes
- **Referencia**: 300–400 consultas/instancia/mes (modelo estándar; menos que 3090).
- **Costo por consulta** (a 400 consultas/mes): **$0.0035–0.0095/consulta**

**Ventajas**: Muy económico (50–70% descuento frente a RunPod).  
**Desventajas**: Preemptible puede interrumpirse (GCP avisa aprox. 30 s antes).

---

## Capacidad por instancia: cuándo la VM se queda corta

Una **sola VM** tiene un tope de consultas/mes según GPU y tipo de modelo. Por encima de ese volumen hay que **añadir más instancias** (escalar).

| Tipo GPU | Modelo | Capacidad orientativa (consultas/mes por instancia) | La VM se queda corta cuando… |
|----------|--------|-----------------------------------------------------|-----------------------------|
| **GPU recomendada** (RunPod RTX 3090) | Estándar (13B+) | 400–800 | Volumen total > 400–800/mes por instancia |
| **GPU recomendada** (RunPod RTX 3090) | Chico (7B) | 800–1.500 | Volumen total > 800–1.500/mes por instancia |
| **GPU mínima** (GCP T4) | Estándar (13B+) | 300–500 | Volumen total > 300–500/mes por instancia |
| **GPU mínima** (GCP T4) | Chico (7B) | 600–1.000 | Volumen total > 600–1.000/mes por instancia |

*Cifras orientativas* para consulta típica 1.500 tokens; dependen de modelo, batch y uso. A mayor volumen total, se multiplican instancias (coste lineal) y el $/consulta se mantiene en el rango indicado más abajo.

---

## Resumen comparativo (costo real por consulta)

| Plan de Hosting | Costo real (USD/consulta, ref. 400 consultas/mes) | Costo real (USD/instancia/mes) |
|-----------------|---------------------------------------------------|---------------------------------|
| **RunPod RTX 3090** (recomendado) | $0.021 | $8.36 |
| **GCP T4 Preemptible** (más barato) | $0.0035–0.0095 | $1.40–3.78 |

### Comparación con la API de OpenAI: por qué nuestra GPU no es más barata

Asumimos **volumen suficiente** para aprovechar al máximo la VM (coste por consulta en su mínimo). Aun así, **el costo por consulta de nuestra infra nunca llega a ser más barato** que usar la API de OpenAI con GPT-4o mini (aprox. **$0.0005–0.0007/consulta** para 1.500 tokens; ver [api/costos.md](../api/costos.md)): nuestro mínimo está en torno a **$0.0018–0.0035/consulta** (GCP Preemptible con modelo chico o estándar). Es decir, **por costo por consulta, la API gana**.

La opción de correr la IA en **nuestra GPU** se justifica por **privacidad de datos, cumplimiento normativo (p. ej. salud), control del modelo e independencia del proveedor**, no por ahorro frente a la API. Quien solo busque el menor coste por consulta debería valorar la API; quien necesite que los datos no salgan de su entorno o cumplir normativa, nuestra infra.

---

## Costo por consulta según tamaño del prompt (bandas de tokens)

Las cifras del resumen anterior corresponden a una **consulta base** (600–1.500 tokens). Para prompts más cortos o más largos, el costo por consulta escala de forma aproximada con el total de tokens (input + output).

| Banda | Tokens totales (aprox.) | Factor sobre costo base | RunPod 3090 (USD/consulta) | GCP Preemptible (USD/consulta) |
|-------|-------------------------|--------------------------|----------------------------|--------------------------------|
| Consulta corta | 200–600 | ~0,6 | ~$0.013 | ~$0.002–0.006 |
| Consulta base | 600–1.500 | 1 | $0.021 | $0.0035–0.0095 |
| Consulta larga | 1.500–3.000 | ~1,5–2 | ~$0.032–0.042 | ~$0.005–0.019 |

Multiplicar el USD/consulta del plan por el factor de la banda para cada uno de los dos planes.

---

## Modelos pequeños (7B u equivalentes) en nuestra GPU

Con **modelos chicos** (p. ej. 7B parámetros) la inferencia es más rápida: la misma GPU atiende más consultas por hora. El costo mensual del plan no cambia, pero el **costo por consulta** baja porque repartimos el mismo costo fijo entre más consultas. La **capacidad por instancia** sube (ver tabla "Capacidad por instancia"): la VM se queda corta a partir de más consultas/mes.

Supuesto: consulta típica 1.500 tokens; modelo pequeño aprox. 2× más rápido que modelo estándar en la misma GPU ⇒ hasta **2× más consultas** por mes con la misma máquina.

**Costo por consulta aproximado con modelo pequeño** (400 consultas/mes como referencia; si se duplica throughput, equivale a aprox. $ por 800 “slots”):

| Plan | Costo real (USD/consulta) modelo estándar | Con modelo pequeño (2× throughput) |
|------|-------------------------------------------|-------------------------------------|
| **RunPod RTX 3090** (recomendado) | $0.021 | **aprox. $0.010–0.011** |
| **GCP T4 Preemptible** (más barato) | $0.0035–0.0095 | **aprox. $0.0018–0.005** |

*Rango orientativo*: con modelos chicos en nuestra infra, el costo por consulta de 1.500 tokens puede quedar en **aprox. $0.002–0.011/consulta** según plan, comparable en el extremo bajo a API con GPT-4o mini (aprox. $0.0005–0.0007/consulta), pero con costo fijo de GPU ya asumido.

---

## Costo por consulta según volumen (costo real)

Escenarios de **consultas/mes** totales. Cuando el volumen supera la capacidad de una instancia (ver tabla “Capacidad por instancia”), se añaden más instancias y el costo total escala; el **costo por consulta** se mantiene en el rango indicado.

| Volumen (consultas/mes) | ¿1 VM basta? (modelo estándar) | RunPod RTX 3090 (recomendado) | GCP T4 Preemptible (más barato) |
|-------------------------|---------------------------------|-------------------------------|----------------------------------|
| **20** | Sí (1 instancia) | $8.36/mes → **$0.418/consulta** | $1.40–3.78/mes → **$0.07–0.19/consulta** |
| **60.000** | No (aprox. 75–150 instancias) | $1.254/mes → **$0.021/consulta** | $210–567/mes → **$0.0035–0.0095/consulta** |
| **600.000** | No (aprox. 750–1.500 instancias) | $12.540/mes → **$0.021/consulta** | $2.100–5.670/mes → **$0.0035–0.0095/consulta** |

- **20/mes**: 1 instancia suficiente; costo fijo repartido entre pocas consultas → $/consulta alto.
- **60.000 y 600.000/mes**: muchas instancias; la VM se queda corta con una sola, se escala y el $/consulta tiende al mínimo del plan (ref. 400 consultas/instancia).

---

## Cargas adicionales de IA en nuestra infra

Cuando la IA corre en nuestra GPU, estas capacidades suman **más llamadas de inferencia** además de las 400 consultas base. El costo por llamada es el mismo que el costo por consulta del plan elegido.

### Costo real de IA por llamada (referencia por plan)

| Plan | USD por llamada IA (aprox.) |
|------|-----------------------------|
| RunPod RTX 3090 (recomendado) | $0.021 |
| GCP T4 Preemptible (más barato) | $0.0035–0.0095 |

---

### 1. Comunicación previa al turno (pre-turno)

El chat/bot guía al paciente **antes** de sacar el turno. Conversación que puede terminar en turno o no; no estaba contemplada antes en los costos.

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Contactos pre-turno estimados | 1.000/médico/mes (personas que inician para sacar turno) | — |
| Mensajes con IA | 4 mensajes × 40% con IA ⇒ **1.600 llamadas IA** | — |
| **RunPod RTX 3090** ($0.021/llamada) | 1.600 × $0.021 | **aprox. $33.60/médico/mes** |
| **GCP T4 Preemptible** ($0.0035–0.0095/llamada) | 1.600 × ($0.0035–0.0095) | **aprox. $5.60–15.20/médico/mes** |

---

### 2. Conversación pre-consulta (chat para despejar dudas y guiar al paciente)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Mensajes pre-consulta | 400 consultas × 5 mensajes = 2.000; 50% con IA ⇒ **1.000 llamadas IA** | — |
| **RunPod RTX 3090** | 1.000 × $0.021 | **aprox. $21/médico/mes** |
| **GCP T4 Preemptible** | 1.000 × ($0.0035–0.0095) | **aprox. $3.50–9.50/médico/mes** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA/médico/mes | 400 (20 nuevos × 10 + 100 activos × 2) | — |
| **RunPod RTX 3090** | 400 × $0.021 | **aprox. $8.40/médico/mes** |
| **GCP T4 Preemptible** | 400 × ($0.0035–0.0095) | **aprox. $1.40–3.80/médico/mes** |

---

### Resumen: costo real adicional por médico (solo IA en nuestra infra)

| Capacidad | Costo real (USD/médico/mes) |
|-----------|-----------------------------|
| Comunicación previa al turno (pre-turno) | $5.60–33.60 (según plan) |
| Conversación pre-consulta | $3.50–21 (según plan) |
| Agente onboarding y día a día | $0.80–5.60 (según plan) |
| **Subtotal IA en infra** | **aprox. $10–60/médico/mes** (RunPod a GCP) |

**Nota**: Medios (STT, Vision) y videollamadas no consumen GPU nuestra; su coste es por API y figura en [api/costos.md](../api/costos.md).

**Cálculo personalizado**:
```
Costo real por consulta = Costo mensual del plan ÷ Consultas por médico por mes
```

---

## Referencias

- [infra/estrategias.md](estrategias.md) – Cómo reducir coste de infra.
- [api/costos.md](../api/costos.md) – Costes cuando se usa API (STT, Vision, video, IA vía API).
