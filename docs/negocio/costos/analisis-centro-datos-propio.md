# Análisis — Centro de datos propio con hardware comprado (5.000 profesionales)

**Estado:** análisis **abierto**, sin compra ni decisión.  
**Fecha de captura:** 2026-11-11. Precios **de orden de magnitud**: revalidar con cotización.  
**Escala:** **5.000 profesionales**, 500 atenciones/mes cada uno ⇒ **2,5 M atenciones/mes**.  
**Supuesto:** se **compra** todo el hardware, GPU incluidas. No se evalúan equipos alquilados. La referencia de contraste es el costo actual con servicios gestionados.

## Conclusión

1. **A 5.000 profesionales, no.** Comprar el hardware y operarlo cuesta entre **43 % y 67 % más** que hoy, según cuánta IA se autohospede.
2. **La sala y los hosts, no las placas, son lo caro:** 450.000 de obra frente a 272.000 de hardware base y 25.000–40.000 por cada servidor de GPU.
3. **De las GPU, solo se justifica comprar para transcribir voz**, y si el objetivo es el LLM, el modelo es **14B**, porque nuestros prompts no pasan de 2.000 tokens.
4. **Reabrir a partir de ~25.000 profesionales.** Antes de eso, cualquier compra es un costo fijo grande para un ahorro que no existe.

---

## 1. Carga a 5.000 profesionales

| Dimensión | Supuesto | Volumen mensual |
|-----------|----------|----------------:|
| Atenciones | 500 × 5.000 | **2,5 M** (~83.000/día) |
| Llamadas de IA | ~4.785 por profesional | **~24 M** |
| Tokens de IA | ~6,06 M por profesional (4,48 M in + 1,58 M out) | **~30,3 B** |
| Minutos de voz a transcribir | 5 min de dictado + 30 % × 4 min de motivos; el audio del video reemplaza, no suma | **~15,5 M min** |
| Videollamada | 40 % × 12 min | **1,0 M teleconsultas** |
| Fotos clínicas | 2 por atención | **5,0 M** |
| Datos nuevos (video 1 pista + fotos + audio + base) | acumulan, no se purgan | **110–220 TB/mes** |
| Peticiones HTTP | ~40 por atención | ~100 M |

Las dos cargas no comparten hardware ni cuello de botella:

| | **IA (inferencia)** | **Videollamada** |
|---|---|---|
| Hardware | GPU (VRAM y ancho de banda) | CPU + red |
| Unidad de capacidad | tokens/s y minutos de audio/hora | llamadas concurrentes y pistas |
| Cuello de botella | utilización de la GPU | egreso y almacenamiento |
| Potencia por nodo | 3–5 kW | 0,3–0,8 kW |
| ¿Necesita GPU? | **sí, siempre** | **no** |
| Necesidad a 5.000 | 8–34 GPU según modelo | 8 nodos, 3 Gbps, 80–150 TB calientes |

---

## 2. Qué hay que comprar

### 2.1 GPU para IA: marcas y modelos

| Modelo | VRAM | Potencia | Precio unitario (USD) | Uso | Unidades a 5.000 |
|--------|------|---------:|----------------------:|-----|-----------------:|
| **NVIDIA L40S** | 48 GB GDDR6 | 350 W | 8.000–9.500 | Inferencia general: LLM 14B y STT | **14–19** (8–11 LLM + 6–8 STT) |
| **NVIDIA RTX 6000 Ada** | 48 GB GDDR6 | 300 W | 7.000–8.500 | Igual que L40S, sin NVLink, más barata | alternativa |
| **NVIDIA L4** | 24 GB GDDR6 | 72 W | 2.500–3.000 | STT; la más barata recomendada | 20–24 |
| **NVIDIA RTX 5090** | 32 GB GDDR7 | 575 W | 2.300–2.900 | Alternativa de consumo; **sin ECC** | 8 (14B) · 8 (STT) |

Un modelo de 14B cuantizado a 4 bits ocupa ~8 GB de VRAM: **cualquiera de estas placas lo aloja cuatro veces**. La elección pasa por rendimiento y consumo, no por memoria.

**Estos precios son de la placa sola.** El servidor que la aloja —CPU, RAM, NVMe, chasis, fuentes y red— agrega **16.000–26.000 por nodo** con L4 y **25.000–40.000** con L40S (§2.2).

Cómo se llega a esos números:

| Paso | Cálculo | Resultado |
|------|---------|-----------|
| Ritmo medio de inferencia | 30,3 B tokens ÷ 2,59 M s | **~11.700 tokens/s** |
| Pico | ×2,5–3,5 | **29.000–41.000 tokens/s** |
| Rendimiento por GPU, modelo 14B | batch continuo | ~3.000–5.000 tokens/s |
| STT con Whisper large-v3 | 15,5 M min a 40–60× tiempo real | 6–13 GPU |

**La cuenta que importa:** si se autohospedan **LLM 14B y STT**, hacen falta **14–19 L40S**. Como se compran en nodos de 8, eso son **2 nodos (16 placas)** si el rendimiento cae en el extremo bajo, o **3 nodos (24 placas)** para cubrir el extremo alto con redundancia. El capex de §3 usa 3 nodos.

Notas de compra:

- **Tarjetas de consumo (RTX 5090) no tienen ECC ni NVLink** y llegan a 575 W cada una. Sirven para probar; no para un servicio clínico con SLA.
- **Obsolescencia a 3 años**, no a 5 como un servidor.

### 2.2 Nodo de GPU: qué se compra además de la placa

La placa no funciona sola. Cada nodo es un servidor que la aloja, y el host cuesta entre un tercio y la mitad de las ocho placas.

#### Nodo con la placa más cara recomendada — 8 × L40S

| Componente | Detalle | USD |
|------------|---------|----:|
| **8 placas L40S** | 48 GB cada una | 64.000–76.000 |
| 2 CPU servidor | 32 cores cada uno | 4.000–8.000 |
| Memoria RAM | 1 TB DDR5 ECC | 8.000–12.000 |
| Almacenamiento local | 2 × 3,84 TB NVMe (sistema y caché de modelo) | 1.500–2.500 |
| Chasis + backplane | 4U, 8 ranuras PCIe dobles, flujo de aire para 3,4 kW | 6.000–10.000 |
| Fuentes redundantes | 4 × 3.000 W, 2+2 | 2.500–4.000 |
| Red | 2 × 100 GbE (tensor parallel y carga de modelos) | 1.500–2.500 |
| **Total por nodo** | | **~89.000–116.000** |

El capex de §3 usa **115.000 por nodo**, en el extremo alto, para no subestimar.

#### Nodo con la placa más barata recomendada — 8 × L4

| Componente | Detalle | USD |
|------------|---------|----:|
| **8 placas L4** | 24 GB cada una, 72 W | 20.000–24.000 |
| 2 CPU servidor | 32 cores cada uno | 4.000–8.000 |
| Memoria RAM | 512 GB DDR5 ECC — la VRAM es el límite, no la RAM | 4.000–6.000 |
| Almacenamiento local | 2 × 3,84 TB NVMe | 1.500–2.500 |
| Chasis + backplane | 2U: la L4 es de perfil bajo y una sola ranura | 4.000–6.000 |
| Fuentes redundantes | 2 × 1.600 W | 1.500–2.500 |
| Red | 2 × 25 GbE (basta para lote y carga de modelos) | 800–1.500 |
| **Total por nodo** | | **~36.000–50.000** |

#### Cuál conviene

| | 8 × L40S | 8 × L4 |
|---|---:|---:|
| Costo del nodo | 89.000–116.000 | **36.000–50.000** |
| Potencia del nodo | 3,4 kW | **1,1 kW** |
| Velocidad relativa en Whisper | 1× | ~1/3 |
| Nodos para los 15,5 M min | **1** | **3** (24 placas) |
| **Costo total para el mismo trabajo** | **89.000–116.000** | **108.000–150.000** |
| Potencia total | 3,4 kW | 3,3 kW |

**La placa más barata no da el nodo más barato.** Por placa el L4 cuesta un tercio, pero rinde un tercio: cubrir la misma transcripción exige **tres nodos**, y los tres hosts se pagan igual. El resultado se da vuelta —**108.000 contra 89.000**— y la potencia consumida termina siendo la misma.

Al revés, si el trabajo fuera chico (una sola institución, poco volumen), el L4 sí gana: se compra un nodo de 36.000 en lugar de uno de 89.000, sin pagar capacidad que nadie usa.

Consecuencias de comprar por nodos:

- **Un nodo de 8 L40S consume 3,4 kW.** Exige racks de 10–15 kW: dos nodos no entran en un rack estándar de 5–6 kW. Un nodo de L4 es de 1,1 kW y entra en un rack común.
- **No se compra «media placa».** Si el cálculo da 14 GPU, se compran 16 (2 nodos) o 24 (3 nodos). El capex se redondea siempre hacia arriba.
- **El host es reutilizable, la placa no.** A los 3 años se cambian las placas; el chasis, la RAM y las fuentes siguen sirviendo.

### 2.3 Videollamada: nada de GPU

| Componente | Cantidad | Detalle |
|------------|---------:|---------|
| Servidores SFU/TURN | 8 | 16 cores / 64 GB / 10 GbE; ~100 llamadas concurrentes por nodo |
| Tránsito | 3 Gbps | 250 TB/mes de egreso; pico 2–2,5 Gbps |
| Grabación | — | Track Egress **muxea sin re-codificar**: CPU liviana, **sin GPU** |

Comprar placas «para el video» es comprar para otra cosa: en esta arquitectura la media se resuelve con CPU y ancho de banda.

### 2.4 Base, aplicación y almacenamiento

| Componente | Cantidad | USD |
|------------|---------:|----:|
| Servidores de aplicación (16c / 128 GB / NVMe) | 6 | 60.000 |
| Servidores de base de datos (32c / 512 GB / NVMe) | 2 | 44.000 |
| Servidores de medios SFU/TURN (16c / 64 GB / 10 GbE) | 8 | 48.000 |
| Nodos de storage caliente (24×22 TB + 25 GbE) | 2 | 32.000 |
| Switches 25 GbE + firewalls | 2 + 2 | 28.000 |
| Librería LTO-9 + cintas iniciales | 1 | 45.000 |
| UPS de rack, repuestos y miscelánea | — | 15.000 |
| **Subtotal hardware sin GPU** | | **272.000** |

---

## 3. Inversión total (USD)

| Escenario | Hardware base | Sala | Nodos de GPU | Migración | **Total** |
|-----------|--------------:|-----:|-------------:|----------:|----------:|
| IA por API | 272.000 | 450.000 | — | 40.000 | **762.000** |
| STT propio (1 nodo = 8 L40S) | 272.000 | 450.000 | 115.000 | 40.000 | **877.000** |
| LLM 14B + STT (14–19 GPU ⇒ 3 nodos = 24 L40S) | 272.000 | 450.000 | 345.000 | 40.000 | **1.107.000** |

La columna **Nodos de GPU** es nodo completo (8 placas + host, §2.2) a 115.000 cada uno: no es el precio de las placas sueltas. La sala (obra, UPS, frío N+1, generador, supresión, control de acceso) va de **350.000 a 700.000**; se toma 450.000 como punto medio. Cuesta más que todo el hardware junto.

---

## 4. Costo mensual de operar

| Concepto | USD/mes |
|----------|--------:|
| Personal (2 FTE con guardia; 2,5 si hay GPU) | 10.000–17.500 |
| Energía (12–29 kW de IT, PUE 1,6, ~0,11 USD/kWh) | 1.500–3.700 |
| Conectividad de dos carriers | 2.500–4.000 |
| Mantenimiento de UPS, frío y generador | 1.500–2.500 |
| Seguros | 800–1.200 |
| Herramientas y monitoreo | 400 |
| Reposición de hardware (capex ÷ 60 meses; GPU ÷ 36) | 12.700–28.700 |

---

## 5. Total a cinco años

| Escenario | USD/mes | USD/prof/mes | 5 años | vs. hoy |
|-----------|--------:|-------------:|-------:|--------:|
| **Referencia: servicios gestionados + IA por API** | **29.194** | **5,84** | **1.752.000** | — |
| Sala propia, IA por API | 48.892 | 9,78 | 3.696.000 | **+67 %** |
| Sala propia, STT propio | 41.669 | 8,33 | 3.377.000 | **+43 %** |
| Sala propia, LLM 14B + STT | 43.510 | 8,70 | 3.718.000 | **+49 %** |

**Variante sin construir sala** (mismo hardware comprado, instalado en instalaciones de terceros): se ahorran los 450.000 de obra y se pagan 4.000–4.500/mes de espacio y energía. Total **32.461/mes** (6,49 por profesional), **+11 %** contra hoy. Es el único escenario que se acerca al empate.

Que el escenario con **LLM 14B** cueste más que el de **solo STT** no es un error: las dos GPU extra para texto cuestan más por mes que los 5.400 de API que reemplazan.

**La retención decide el archivo.** Si la grabación se borra a los **90 días** en lugar de conservarse años, el almacenamiento deja de crecer: el ahorro de comprar cae y el punto de reapertura se aleja a un volumen que no está en el plan.

---

## 6. ¿Se justifican las GPU?

Compra versus lo que cuesta hoy por API, con redundancia mínima (un nodo caído no puede tirar el servicio):

| Carga | Costo API hoy | Compra (capex a 36 meses + operación) | Veredicto |
|-------|--------------:|----------------------------------------:|-----------|
| **LLM 14B** (1–2 nodos, 8–16 L40S) | 5.400 | **5.100–10.200** | **No**: empata como mucho y con peor modelo; con redundancia, 2× |
| **STT Whisper** (1 nodo, 8 L40S) | 10.850 | **3.700–5.100** | **Sí**: ahorra 55–65 % |

La regla: **la GPU propia gana cuando la utilización pasa del ~35 %**. El chat interactivo no llega —se dimensiona para el pico y queda ocioso—; el STT por lotes sí, porque se encola y corre de noche.

Y hay una asimetría que decide: **un nodo de STT caído solo atrasa la cola; un nodo de LLM caído corta el chat**. Por eso el STT tolera redundancia barata (N+1 sobre lote) y el LLM no.

Advertencias sobre el único caso favorable:

- **El rendimiento se mide, no se supone.** Con L40S a 60× tiempo real hace falta un nodo y el ahorro es ~53 %; a 40× hacen falta dos y el ahorro cae a ~6 %. Un benchmark de 100 horas de audio real —probando L40S y L4— resuelve la duda antes de comprar.
- **Cambia el contrato de calidad**: versiones del modelo, idioma, puntuación, números y diarización pasan a ser responsabilidad nuestra.
- **No elimina el costo de IA**: el LLM sigue por API.

### 6.1 El modelo: 14B, y solo para lo mecánico

**Todos los prompts del catálogo entran en 2.000 tokens y ninguno pide razonamiento largo.** El modelo en producción hoy es de esa misma clase, así que un 14B no es un salto hacia atrás: es el mismo escalón.

| Criterio | 14B |
|----------|-----|
| Costo contra la API | empata como mucho |
| Contextos actuales (≤1.850 tokens in, ≤600 out) | alcanza y sobra |
| VRAM por réplica (cuantizado 4 bits) | ~8 GB: una placa lo aloja cuatro veces |
| Latencia en chat interactivo | buena |
| Dónde falla | razonamiento clínico en varios pasos |

Si igual se autohospeda, conviene **repartir por contexto**, no poner el modelo en todo:

| Dónde | Contextos | Llamadas por profesional | Costo |
|-------|-----------|-------------------------:|------:|
| **14B en casa** | preprocess, guide, onboarding, clasificación, resumen de motivos | 3.485 (73 %) | — |
| **API** | insights de motivos, análisis de consulta, codificación | 1.300 (27 %) | ~1.740/mes a 5.000 |

Total híbrido: **6.840–11.940/mes** contra **5.400** de API pura. **Sigue perdiendo.** La razón para autohospedar es custodia de datos, no costo.

---

## 7. Lo que puede mover el número

| Factor | Efecto |
|--------|--------|
| Retención de video | La palanca más grande del almacenamiento; a 90 días el archivo frío casi desaparece |
| Tarifa eléctrica | Cada 0,02 USD/kWh mueve ~500/mes en sala propia |
| Tipo de cambio y aduana | El hardware se compra en dólares; el personal se paga en pesos |
| Benchmark de STT | 40× en lugar de 60× duplica los nodos necesarios |
| Redundancia exigida | Un segundo sitio para HA duplica el fijo; no está incluido arriba |

---

## 8. Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Corte de energía o térmico | UPS + generador con combustible + pruebas mensuales |
| Inundación o siniestro local | Sitio en altura, segundo sitio, respaldo externo |
| Falla de disco o controladora | RAID + repuestos en sitio + garantía 4 h |
| Ransomware sobre datos clínicos | Copia inmutable fuera del sitio; cinta con bóveda externa |
| Rotación del personal de infraestructura | Runbooks, documentación, soporte externo de respaldo |
| GPU subutilizada | Encolar lotes y medir; por debajo del 35 % de utilización, volver a API |
| Obsolescencia de GPU a 3 años | Reserva de reposición a 36 meses (ya incluida) |
| Migración fallida | Plan por etapas con vuelta atrás durante 90 días |

---

## 9. Cuándo reabrir

1. **Volumen:** superar **~25.000 profesionales** activos.
2. **Contrato:** una licitación exige custodia física en una instalación concreta y auditable.
3. **STT:** el benchmark rinde **≥ 60× tiempo real** y el gasto de transcripción supera **10.000 USD/mes** sostenidos.
4. **Capex:** hay fondos propios que no compiten con producto ni con contratación, y horizonte de cinco años.
5. **Equipo:** existe un responsable de infraestructura con experiencia en operación 24/7 y un segundo de guardia.

## 10. Recomendación

| Etapa | Escala | Qué hacer |
|-------|-------:|-----------|
| **0 — Ahora** | 0–5.000 | No comprar. Medir costo real por servicio y cerrar el hueco de presupuesto de app + BD + medios (~0,24–0,40 por profesional). |
| **1 — Optimizar** | 5.000 | Retención de video y fotos, audio crudo borrado tras transcribir, ciclo 14 d → archivo frío. **Mueve el costo más que cualquier compra.** |
| **2 — GPU, solo STT** | 5.000 | Benchmark de Whisper con audio real. Si rinde ≥ 60×, comprar **un nodo de 8 L40S** para transcripción y dejar el LLM por API. El nodo de L4 sale más barato de entrada, pero para este volumen pide tres (§2.2). |
| **3 — Revisar** | 25.000 | Reevaluar sala propia completa con los números medidos, no con los de este documento. |

---

## 11. Decisiones abiertas

| # | Tema | Estado |
|---|------|--------|
| 1 | Retención de video: 90 días o años | **Abierto** — máximo impacto económico |
| 2 | 1 vs 2 pistas de video en archivo | **Abierto** — duplica el almacenamiento |
| 3 | Comprar GPU para STT | **Abierto** — depende del benchmark |
| 4 | Calidad de un modelo abierto de 14B frente a la API actual | **Abierto** — si falla en análisis de consulta o codificación, esos dos contextos se quedan en API |
| 5 | Sala propia vs instalaciones de terceros | **Abierto** — 450.000 de diferencia |
| 6 | Segundo sitio para alta disponibilidad | **Abierto** — no incluido en el capex |

---

## Referencias

- [analisis-videollamada-self-host.md](analisis-videollamada-self-host.md) — arquitectura de medios, storage y COGS de video.
- [costos-api.md](costos-api.md) — tarifas por token y por minuto; catálogo de contextos.
- [infra-costos.md](infra-costos.md) — GPU por servicio de inferencia (eje distinto: no es compra).
- [impuestos-argentina.md](impuestos-argentina.md) — impacto fiscal sobre el costo operativo.
- [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) — precio de lista y margen.
