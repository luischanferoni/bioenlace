# Costos – Infraestructura (nuestra GPU)

Este documento refleja el **costo real** de la infraestructura cuando la IA corre en **nuestra GPU** (RunPod, AWS, GCP), **sin aplicar** estrategias de reducción. Las reducciones posibles se documentan en [infra/estrategias.md](estrategias.md).

## Supuestos base

- **Consultas por médico**: 20/día = 400/mes (20 × 20 días).
- **Consulta típica**: 600–1.500 tokens totales (input + output). A mayor cantidad de tokens, mayor costo por consulta (más tiempo de GPU por inferencia).
- **Costo real**: se indica por plan y por ítem en las tablas siguientes. Las cifras asumen un **modelo de tamaño estándar**; con **modelos pequeños** (7B u equivalentes) el costo por consulta baja (ver sección [Modelos pequeños](#modelos-pequeños-7b-u-equivalentes-en-nuestra-gpu)).

---

## Planes de hosting GPU

### Plan 1: RunPod RTX 3090 (Recomendado)

- **Costo**: $8.36/médico/mes
- **Consultas/mes**: 400
- **Costo por consulta**: $8.36 ÷ 400 ≈ **$0.021/consulta**

**Ventajas**: Precio fijo, sin interrupciones, fácil de configurar.  
**Desventajas**: Escalado manual, menos servicios que AWS/GCP.

---

### Plan 2: RunPod RTX 4090

- **Costo**: $8.43/médico/mes | **Costo por consulta**: **$0.021/consulta**
- **Ventajas**: GPU más potente. **Desventajas**: Algo más caro que 3090, escalado manual.

---

### Plan 3: AWS g4dn.xlarge (Reserved)

- **Costo**: $4.56–6.84/médico/mes
- **Costo por consulta**: **$0.008–0.011/consulta**
- **Ventajas**: Escalado automático, alta disponibilidad, ~40% descuento con reserva 1 año.  
**Desventajas**: Compromiso 1 año.

---

### Plan 4: AWS g4dn.xlarge (Spot)

- **Costo**: $1.52–4.56/médico/mes
- **Costo por consulta**: **$0.0025–0.008/consulta**
- **Ventajas**: Muy económico (60–80% descuento).  
**Desventajas**: Spot puede interrumpirse (AWS avisa 2 min antes).

---

### Plan 5: GCP T4 (Preemptible)

- **Costo**: $1.40–3.78/médico/mes
- **Costo por consulta**: **$0.002–0.006/consulta**
- **Ventajas**: Muy económico (50–70% descuento).  
**Desventajas**: Preemptible puede interrumpirse (GCP avisa ~30 s antes).

---

## Resumen comparativo (costo real por consulta)

| Plan de Hosting | Costo real (USD/consulta) | Costo real (USD/médico/mes, 400 consultas) |
|-----------------|---------------------------|---------------------------------------------|
| **RunPod RTX 3090** | $0.021 | $8.36 |
| **RunPod RTX 4090** | $0.021 | $8.43 |
| **AWS Reserved** | $0.011–0.017 | $4.56–6.84 |
| **AWS Spot** | $0.0038–0.011 | $1.52–4.56 |
| **GCP Preemptible** | $0.0035–0.0095 | $1.40–3.78 |

---

## Costo por consulta según tamaño del prompt (bandas de tokens)

Las cifras del resumen anterior corresponden a una **consulta base** (600–1.500 tokens). Para prompts más cortos o más largos, el costo por consulta escala de forma aproximada con el total de tokens (input + output).

| Banda | Tokens totales (aprox.) | Factor sobre costo base | RunPod 3090 (USD/consulta) | GCP Preemptible (USD/consulta) |
|-------|-------------------------|--------------------------|----------------------------|--------------------------------|
| Consulta corta | 200–600 | ~0,6 | ~$0.013 | ~$0.002–0.006 |
| Consulta base | 600–1.500 | 1 | $0.021 | $0.0035–0.0095 |
| Consulta larga | 1.500–3.000 | ~1,5–2 | ~$0.032–0.042 | ~$0.005–0.019 |

El mismo factor se aplica al costo por consulta de los demás planes (AWS Reserved, Spot): multiplicar el USD/consulta del plan por el factor de la banda.

---

## Modelos pequeños (7B u equivalentes) en nuestra GPU

Con **modelos chicos** (p. ej. 7B parámetros) la inferencia es más rápida: la misma GPU atiende más consultas por hora. El costo mensual del plan no cambia, pero el **costo por consulta** baja porque repartimos el mismo costo fijo entre más consultas.

Supuesto: consulta típica ~1.500 tokens; modelo pequeño ~2× más rápido que modelo grande en la misma GPU ⇒ hasta ~**2× más consultas** por mes con la misma máquina, o bien mismo volumen con menor uso de GPU.

**Costo por consulta aproximado con modelo pequeño** (400 consultas/mes como referencia; si se duplica throughput, equivale a ~$ por 800 “slots”):

| Plan de Hosting | Costo real (USD/consulta) modelo estándar | Con modelo pequeño (~2× throughput) |
|-----------------|-------------------------------------------|-------------------------------------|
| **RunPod RTX 3090** | $0.021 | **~$0.010–0.011** |
| **RunPod RTX 4090** | $0.021 | **~$0.010–0.011** |
| **AWS Reserved** | $0.011–0.017 | **~$0.0055–0.0085** |
| **AWS Spot** | $0.0038–0.011 | **~$0.002–0.0055** |
| **GCP Preemptible** | $0.0035–0.0095 | **~$0.0018–0.005** |

*Rango orientativo*: con modelos chicos en nuestra infra, el costo por consulta de ~1.500 tokens puede quedar en **~$0.002–0.011/consulta** según plan, comparable en el extremo bajo a API con GPT-4o mini (~$0.0005–0.0007/consulta), pero con costo fijo de GPU ya asumido.

---

## Costo por consulta según volumen (costo real)

### 10 consultas/día (310/mes)

| Plan | USD/consulta | USD/médico/mes |
|------|--------------|----------------|
| RunPod RTX 3090 | $0.027 | $8.37 |
| AWS Reserved | $0.015–0.022 | $4.65–6.82 |
| AWS Spot | $0.005–0.015 | $1.55–4.65 |
| GCP Preemptible | $0.005–0.012 | $1.55–3.72 |

### 20 consultas/día (400/mes) – Base

| Plan | USD/consulta | USD/médico/mes |
|------|--------------|----------------|
| RunPod RTX 3090 | $0.021 | $8.36 |
| AWS Reserved | $0.011–0.017 | $4.56–6.84 |
| AWS Spot | $0.0038–0.011 | $1.52–4.56 |
| GCP Preemptible | $0.0035–0.0095 | $1.40–3.78 |

### 30 consultas/día (930/mes)

| Plan | USD/consulta | USD/médico/mes |
|------|--------------|----------------|
| RunPod RTX 3090 | $0.009 | $8.37 |
| AWS Reserved | $0.005–0.007 | $4.65–6.51 |
| AWS Spot | $0.002–0.005 | $1.86–4.65 |
| GCP Preemptible | $0.002–0.004 | $1.86–3.72 |

### 50 consultas/día (1.550/mes)

| Plan | USD/consulta | USD/médico/mes |
|------|--------------|----------------|
| RunPod RTX 3090 | $0.005 | $8.36 |
| AWS Reserved | $0.003–0.004 | $4.65–6.20 |
| AWS Spot | $0.001–0.003 | $1.55–4.65 |
| GCP Preemptible | $0.001–0.002 | $1.55–3.10 |

---

## Cargas adicionales de IA en nuestra infra

Cuando la IA corre en nuestra GPU, estas capacidades suman **más llamadas de inferencia** además de las 400 consultas base. El costo por llamada es el mismo que el costo por consulta del plan elegido.

### Costo real de IA por llamada (referencia por plan)

| Plan | USD por llamada IA (aprox.) |
|------|-----------------------------|
| RunPod RTX 3090/4090 | $0.021 |
| AWS Reserved | $0.011–0.017 |
| AWS Spot | $0.0038–0.011 |
| GCP Preemptible | $0.0035–0.0095 |

---

### 1. Comunicación previa al turno (pre-turno)

El chat/bot guía al paciente **antes** de sacar el turno. Conversación que puede terminar en turno o no; no estaba contemplada antes en los costos.

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Contactos pre-turno estimados | ~1.000/médico/mes (personas que inician para sacar turno) | — |
| Mensajes con IA | 4 mensajes × 40% con IA ⇒ **~1.600 llamadas IA** | — |
| **RunPod** ($0.021/llamada) | 1.600 × $0.021 | **~$33.60/médico/mes** |
| **AWS Reserved** ($0.014/llamada) | 1.600 × $0.014 | **~$22.40/médico/mes** |
| **AWS Spot** ($0.0038–0.011/llamada) | 1.600 × ($0.0038–0.011) | **~$6.10–17.60/médico/mes** |
| **GCP Preemptible** ($0.0035–0.0095/llamada) | 1.600 × ($0.0035–0.0095) | **~$5.60–15.20/médico/mes** |

---

### 2. Conversación pre-consulta (chat para despejar dudas y guiar al paciente)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Mensajes pre-consulta | 400 consultas × 5 mensajes = 2.000; 50% con IA ⇒ **1.000 llamadas IA** | — |
| **RunPod** | 1.000 × $0.021 | **~$21/médico/mes** |
| **AWS Reserved** | 1.000 × $0.014 | **~$14/médico/mes** |
| **AWS Spot** | 1.000 × ($0.0038–0.011) | **~$3.80–11/médico/mes** |
| **GCP Preemptible** | 1.000 × ($0.0035–0.0095) | **~$3.50–9.50/médico/mes** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA/médico/mes | ~400 (20 nuevos × 10 + 100 activos × 2) | — |
| **RunPod** | 400 × $0.021 | **~$8.40/médico/mes** |
| **AWS Reserved** | 400 × $0.014 | **~$5.60/médico/mes** |
| **AWS Spot** | 400 × ($0.0038–0.011) | **~$1.50–4.40/médico/mes** |
| **GCP Preemptible** | 400 × ($0.0035–0.0095) | **~$1.40–3.80/médico/mes** |

---

### Resumen: costo real adicional por médico (solo IA en nuestra infra)

| Capacidad | Costo real (USD/médico/mes) |
|-----------|-----------------------------|
| Comunicación previa al turno (pre-turno) | $5.60–33.60 (según plan) |
| Conversación pre-consulta | $3.50–21 (según plan) |
| Agente onboarding y día a día | $0.80–5.60 (según plan) |
| **Subtotal IA en infra** | **~$10–60/médico/mes** (según plan) |

**Nota**: Medios (STT, Vision) y videollamadas no consumen GPU nuestra; su coste es por API y figura en [api/costos.md](../api/costos.md).

**Cálculo personalizado**:
```
Costo real por consulta = Costo mensual del plan ÷ Consultas por médico por mes
```

---

## Referencias

- [infra/estrategias.md](estrategias.md) – Cómo reducir coste de infra.
- [api/costos.md](../api/costos.md) – Costes cuando se usa API (STT, Vision, video, IA vía API).
