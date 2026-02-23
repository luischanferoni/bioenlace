# Costos – Infraestructura (nuestra GPU)

Este documento refleja el **costo real** de la infraestructura cuando la IA corre en **nuestra GPU** (RunPod, AWS, GCP), **sin aplicar** estrategias de reducción. Las reducciones posibles se documentan en [infra/estrategias.md](estrategias.md).

## Supuestos base

- **Consultas por médico**: 20/día = 600/mes (31 días).
- **Costo real**: se indica por plan y por ítem en las tablas siguientes.

---

## Planes de hosting GPU

### Plan 1: RunPod RTX 3090 (Recomendado)

- **Costo**: $8.36/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: $8.36 ÷ 600 ≈ **$0.014/consulta**

**Ventajas**: Precio fijo, sin interrupciones, fácil de configurar.  
**Desventajas**: Escalado manual, menos servicios que AWS/GCP.

---

### Plan 2: RunPod RTX 4090

- **Costo**: $8.43/médico/mes | **Costo por consulta**: **$0.014/consulta**
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

| Plan de Hosting | Costo real (USD/consulta) | Costo real (USD/médico/mes, 600 consultas) |
|-----------------|---------------------------|---------------------------------------------|
| **RunPod RTX 3090** | $0.014 | $8.36 |
| **RunPod RTX 4090** | $0.014 | $8.43 |
| **AWS Reserved** | $0.008–0.011 | $4.56–6.84 |
| **AWS Spot** | $0.0025–0.008 | $1.52–4.56 |
| **GCP Preemptible** | $0.002–0.006 | $1.40–3.78 |

---

## Costo por consulta según volumen (costo real)

### 10 consultas/día (310/mes)

| Plan | USD/consulta | USD/médico/mes |
|------|--------------|----------------|
| RunPod RTX 3090 | $0.027 | $8.37 |
| AWS Reserved | $0.015–0.022 | $4.65–6.82 |
| AWS Spot | $0.005–0.015 | $1.55–4.65 |
| GCP Preemptible | $0.005–0.012 | $1.55–3.72 |

### 20 consultas/día (600/mes) – Base

| Plan | USD/consulta | USD/médico/mes |
|------|--------------|----------------|
| RunPod RTX 3090 | $0.014 | $8.36 |
| AWS Reserved | $0.008–0.011 | $4.56–6.84 |
| AWS Spot | $0.0025–0.008 | $1.52–4.56 |
| GCP Preemptible | $0.002–0.006 | $1.40–3.78 |

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

Cuando la IA corre en nuestra GPU, estas capacidades suman **más llamadas de inferencia** además de las 600 consultas base. El costo por llamada es el mismo que el costo por consulta del plan elegido.

### Costo real de IA por llamada (referencia por plan)

| Plan | USD por llamada IA (aprox.) |
|------|-----------------------------|
| RunPod RTX 3090/4090 | $0.014 |
| AWS Reserved | $0.008–0.011 |
| AWS Spot | $0.0025–0.008 |
| GCP Preemptible | $0.002–0.006 |

---

### 1. Comunicación previa al turno (pre-turno)

El chat/bot guía al paciente **antes** de sacar el turno. Conversación que puede terminar en turno o no; no estaba contemplada antes en los costos.

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Contactos pre-turno estimados | ~1.000/médico/mes (personas que inician para sacar turno) | — |
| Mensajes con IA | 4 mensajes × 40% con IA ⇒ **~1.600 llamadas IA** | — |
| **RunPod** ($0.014/llamada) | 1.600 × $0.014 | **~$22.40/médico/mes** |
| **AWS Reserved** ($0.009/llamada) | 1.600 × $0.009 | **~$14.40/médico/mes** |
| **AWS Spot** ($0.0025–0.008/llamada) | 1.600 × ($0.0025–0.008) | **~$4–12.80/médico/mes** |
| **GCP Preemptible** ($0.002–0.006/llamada) | 1.600 × ($0.002–0.006) | **~$3.20–9.60/médico/mes** |

---

### 2. Conversación pre-consulta (chat para despejar dudas y guiar al paciente)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Mensajes pre-consulta | 600 consultas × 5 mensajes = 3.000; 50% con IA ⇒ **1.500 llamadas IA** | — |
| **RunPod** | 1.500 × $0.014 | **~$21/médico/mes** |
| **AWS Reserved** | 1.500 × $0.009 | **~$13.50/médico/mes** |
| **AWS Spot** | 1.500 × ($0.0025–0.008) | **~$3.75–12/médico/mes** |
| **GCP Preemptible** | 1.500 × ($0.002–0.006) | **~$3–9/médico/mes** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA/médico/mes | ~400 (20 nuevos × 10 + 100 activos × 2) | — |
| **RunPod** | 400 × $0.014 | **~$5.60/médico/mes** |
| **AWS Reserved** | 400 × $0.009 | **~$3.60/médico/mes** |
| **AWS Spot** | 400 × ($0.0025–0.008) | **~$1–3.20/médico/mes** |
| **GCP Preemptible** | 400 × ($0.002–0.006) | **~$0.80–2.40/médico/mes** |

---

### Resumen: costo real adicional por médico (solo IA en nuestra infra)

| Capacidad | Costo real (USD/médico/mes) |
|-----------|-----------------------------|
| Comunicación previa al turno (pre-turno) | $3.20–22.40 (según plan) |
| Conversación pre-consulta | $3–21 (según plan) |
| Agente onboarding y día a día | $0.80–5.60 (según plan) |
| **Subtotal IA en infra** | **~$7–49/médico/mes** (según plan) |

**Nota**: Medios (STT, Vision) y videollamadas no consumen GPU nuestra; su coste es por API y figura en [api/costos.md](../api/costos.md).

**Cálculo personalizado**:
```
Costo real por consulta = Costo mensual del plan ÷ Consultas por médico por mes
```

---

## Referencias

- [infra/estrategias.md](estrategias.md) – Cómo reducir coste de infra.
- [api/costos.md](../api/costos.md) – Costes cuando se usa API (STT, Vision, video, IA vía API).
