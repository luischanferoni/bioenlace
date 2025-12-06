# Estimación de Costos de Infraestructura y Hosting

## Resumen Ejecutivo

Este documento proporciona una estimación detallada de los costos mensuales y anuales para el hosting e infraestructura del sistema médico Bioenlace, considerando el uso de HuggingFace como proveedor de modelos de IA.

**Escenario base**: Sistema médico con ~100 consultas/día

**IMPORTANTE**: La mayoría de las consultas médicas (80-90%) se realizan por audio (dictado), que luego se procesa con Speech-to-Text, formateo, estructuración y asociación a términos SNOMED.

**Nota**: Este documento incluye estimaciones de uso por médico y costos por cliente en las secciones correspondientes.

---

## Desglose de Costos por Componente

### 1. Servidor Web/Hosting

#### Opción A: VPS (Recomendado para Producción)
**Proveedores locales (Argentina)**: DonWeb, Hostinger, etc.

**Especificaciones mínimas recomendadas**:
- 2-4 vCPU
- 4-8 GB RAM
- 50-100 GB SSD
- 2-5 TB transferencia

**Costo estimado**: $15-30 USD/mes (~$15,000-30,000 ARS/mes)

#### Opción B: Cloud (AWS/GCP/Azure)
- **AWS Lightsail** (equivalente):
  - 2 vCPU, 4GB RAM, 80GB SSD: ~$20 USD/mes
- **Google Cloud Compute Engine**:
  - e2-medium: ~$25 USD/mes
- **Azure B2s**: ~$22 USD/mes

**Costo estimado**: $20-35 USD/mes

#### Opción C: Hosting Compartido (Solo para desarrollo/pruebas)
⚠️ **No recomendado para producción médica**

**Costo**: $5-10 USD/mes

---

### 2. Base de Datos MySQL

**Si está en el mismo servidor**: Incluido

**Si es separada (Recomendado para producción)**:
- **MySQL Managed** (AWS RDS, Cloud SQL):
  - db.t3.small (2 vCPU, 2GB RAM): ~$25-35 USD/mes
- **Base de datos dedicada en VPS**: Incluida en el VPS

**Costo estimado**: $0-35 USD/mes (según opción)

---

### 3. Almacenamiento

**Desglose de almacenamiento**:
- Archivos de aplicación: ~5-10 GB
- Base de datos: ~10-50 GB (crece con el tiempo)
- Logs y caché: ~5-10 GB
- Backups: ~20-50 GB

**Total estimado**: 50-100 GB

**Costo**: Generalmente incluido en el VPS/Cloud
**Almacenamiento adicional**: $0.10-0.20 USD/GB/mes si se necesita más

---

### 4. Ancho de Banda/Transferencia

**Estimación**: 50-200 GB/mes (según uso)

**Incluido en la mayoría de planes**: 2-5 TB
**Costo adicional**: $0-10 USD/mes (si se excede)

---

### 5. HuggingFace API (Costos de Uso)

**Con las optimizaciones implementadas**:

#### Escenario Conservador (100 consultas/día, 80% por audio)
- **Speech-to-Text** (80 consultas/día): ~$3.20/día = **$96/mes**
- **Corrección de texto** (100 consultas/día): ~$5/día = **$150/mes**
- **Análisis de consultas** (100 consultas/día): ~$3/día = **$90/mes**
- **Embeddings SNOMED**: **$0/mes** (gratis con modelo seleccionado)

**Total HuggingFace**: ~**$336 USD/mes**

#### Escenario Optimista (con caché efectivo 60%)
- **Speech-to-Text**: ~$1.92/día = **$57.60/mes** (caché bajo en audio, ~20%)
- **Corrección y análisis**: ~$4.80/día = **$144/mes** (caché 50-60%)
- **Total HuggingFace**: ~**$201.60 USD/mes**

**Nota**: Estos costos pueden variar significativamente según:
- Tasa de aciertos en caché
- Volumen real de consultas
- Uso de funcionalidades de audio
- Modelos seleccionados

---

### 6. Dominio y SSL

- **Dominio .com.ar**: ~$10-20 USD/año (~$1-2 USD/mes)
- **SSL (Let's Encrypt)**: Gratis
- **SSL comercial**: $5-15 USD/año

**Costo**: $1-3 USD/mes

---

### 7. Backups

- **Backup automático en VPS**: Incluido o $5-10 USD/mes
- **Backup en cloud storage** (S3, etc.): ~$2-5 USD/mes

**Costo**: $0-10 USD/mes

---

### 8. Monitoreo y Herramientas

- **Monitoreo básico** (UptimeRobot, etc.): Gratis o $5-10 USD/mes
- **Logs centralizados** (opcional): $10-20 USD/mes

**Costo**: $0-20 USD/mes

---

## Resumen de Costos Mensuales

### Escenario Mínimo (Hosting Básico)
| Componente | Costo Mensual (USD) |
|------------|---------------------|
| VPS básico | $15 |
| HuggingFace API (optimizado, mayoría audio) | $135 |
| Dominio/SSL | $2 |
| Backups | $5 |
| **TOTAL** | **~$157 USD/mes** |
| **TOTAL (ARS)** | **~$157,000 ARS/mes** |

### Escenario Recomendado (Producción)
| Componente | Costo Mensual (USD) |
|------------|---------------------|
| VPS/Cloud medio | $25 |
| Base de datos separada | $30 |
| HuggingFace API (optimizado, mayoría audio) | $200 |
| Dominio/SSL | $2 |
| Backups | $10 |
| Monitoreo | $10 |
| **TOTAL** | **~$277 USD/mes** |
| **TOTAL (ARS)** | **~$277,000 ARS/mes** |

### Escenario Premium (Alta Disponibilidad)
| Componente | Costo Mensual (USD) |
|------------|---------------------|
| Cloud con alta disponibilidad | $50 |
| Base de datos replicada | $60 |
| HuggingFace API | $200 |
| CDN (opcional) | $20 |
| Backups avanzados | $15 |
| Monitoreo completo | $20 |
| **TOTAL** | **~$365 USD/mes** |
| **TOTAL (ARS)** | **~$365,000 ARS/mes** |

---

## Proyección Anual

### Escenario Recomendado
**Total anual**: ~**$3,324 USD/año** (~$3.3M ARS/año)

**Desglose porcentual**:
- **Infraestructura**: ~$924 USD/año (28%)
- **HuggingFace API**: ~$2,400 USD/año (72%)

---

## Factores que Afectan los Costos

### 1. Volumen de Consultas
- **Más consultas** = Más costos en HuggingFace
- **Impacto**: Directo y proporcional

### 2. Efectividad del Caché
- **Mejor caché** = Menos llamadas a API
- **Impacto**: Puede reducir costos de HuggingFace en 30-50%

### 3. Uso de Audio
- **Speech-to-Text** es el componente principal (80-90% de consultas)
- **Impacto**: ~$57-96 USD/mes por médico según volumen
- **Nota**: El caché es menos efectivo en audio (solo ~20% hit rate) porque cada dictado es único

### 4. Crecimiento de Datos
- **Más almacenamiento** con el tiempo
- **Impacto**: +$5-20 USD/mes cada año

### 5. Tráfico
- **Más usuarios** = Más ancho de banda
- **Impacto**: Generalmente incluido, pero puede requerir upgrade

---

## Recomendaciones para Reducir Costos

### 1. Infraestructura
- ✅ Empezar con VPS básico y escalar según necesidad
- ✅ Considerar hosting local (proveedores argentinos pueden ser más económicos)
- ✅ Usar backups incrementales para reducir espacio

### 2. HuggingFace API
- ✅ **Optimizar caché**: Ajustar TTL según patrones de uso
- ✅ **Monitorear uso**: Revisar logs semanalmente
- ✅ **Usar modelos económicos por defecto**: Cambiar a premium solo si es necesario
- ✅ **Aprovechar deduplicación**: El sistema ya lo implementa automáticamente
- ✅ **Batch processing**: Agrupar requests cuando sea posible

### 3. Base de Datos
- ✅ Optimizar queries para reducir carga
- ✅ Usar índices apropiados
- ✅ Limpiar logs antiguos regularmente

### 4. Almacenamiento
- ✅ Limpiar caché antiguo periódicamente
- ✅ Comprimir backups
- ✅ Usar almacenamiento de objetos para archivos grandes

---

## Comparación: Con vs Sin Optimizaciones

### Sin Optimizaciones (estimado)
- **HuggingFace API**: ~$900 USD/mes
- **Infraestructura**: ~$50 USD/mes
- **Total**: ~$950 USD/mes

### Con Optimizaciones Implementadas (mayoría por audio)
- **HuggingFace API**: ~$200 USD/mes (78% de reducción)
- **Infraestructura**: ~$50 USD/mes
- **Total**: ~$250 USD/mes

**Ahorro mensual**: ~$700 USD/mes
**Ahorro anual**: ~$8,400 USD/año

**Nota**: El ahorro es menor que sin audio porque Speech-to-Text tiene menor tasa de caché (cada dictado es único)

---

## Plan de Escalamiento

### Fase 1: Inicio (0-50 consultas/día, mayoría por audio)
- **VPS básico**: $15 USD/mes
- **HuggingFace** (con Speech-to-Text): $80-120 USD/mes
- **Total**: ~$95-135 USD/mes

### Fase 2: Crecimiento (50-200 consultas/día, mayoría por audio)
- **VPS medio**: $25 USD/mes
- **Base de datos separada**: $30 USD/mes
- **HuggingFace** (con Speech-to-Text): $200-350 USD/mes
- **Total**: ~$255-405 USD/mes

### Fase 3: Producción (200-500 consultas/día, mayoría por audio)
- **Cloud con alta disponibilidad**: $50 USD/mes
- **Base de datos replicada**: $60 USD/mes
- **HuggingFace** (con Speech-to-Text): $400-700 USD/mes
- **CDN**: $20 USD/mes
- **Total**: ~$530-830 USD/mes

### Fase 4: Escala (500+ consultas/día, mayoría por audio)
- **Infraestructura escalable**: $100+ USD/mes
- **HuggingFace** (con Speech-to-Text): $700+ USD/mes
- **Total**: ~$800+ USD/mes

---

## Notas Importantes

1. **Tipo de cambio**: Los costos en ARS varían según el tipo de cambio USD/ARS
2. **Precios de HuggingFace**: Están en USD y pueden cambiar
3. **Proveedores locales**: Pueden ofrecer precios en ARS más estables
4. **Descuentos por volumen**: Algunos proveedores ofrecen descuentos por pago anual
5. **Costos ocultos**: Considerar costos de migración, configuración inicial, etc.

---

## Monitoreo de Costos

### Métricas Clave a Monitorear

1. **Uso de HuggingFace API**:
   - Requests por día
   - Tasa de caché hits
   - Costo por consulta

2. **Infraestructura**:
   - Uso de CPU/RAM
   - Almacenamiento utilizado
   - Ancho de banda consumido

3. **Base de datos**:
   - Tamaño de la base de datos
   - Queries lentas
   - Conexiones simultáneas

### Herramientas Recomendadas

- **HuggingFace**: Dashboard de uso en su plataforma
- **Servidor**: Grafana + Prometheus (gratis) o servicios comerciales
- **Alertas**: Configurar alertas cuando se acerque a límites

---

## Contacto y Actualización

Este documento debe actualizarse periódicamente según:
- Cambios en precios de proveedores
- Volumen real de uso
- Nuevas optimizaciones implementadas
- Cambios en la arquitectura

**Última actualización**: Diciembre 2024

---

## Estimación de Uso por Médico

### Volumen de Consultas e Interacciones por Médico

#### Consultas Médicas (Análisis de Consultas)
**Descripción**: El médico dicta la consulta en audio, que se procesa con el siguiente flujo:
1. **Audio → Texto** (Speech-to-Text)
2. **Texto → Formateo/Estructuración** (Corrección y análisis)
3. **Asociación a términos SNOMED** (Embeddings y codificación)

**Estimación diaria por médico**:
- **Médico con carga normal**: 15-25 consultas/día
- **Médico con alta carga**: 25-40 consultas/día
- **Promedio**: ~20 consultas/día

**IMPORTANTE**: La mayoría de las consultas serán por audio (80-90% de las consultas)

**Cada consulta médica genera**:
- 1 llamada a Speech-to-Text (audio a texto) - **SIEMPRE** (con caché: ~20% hit rate, audio raramente se repite)
- 1 llamada a corrección de texto (con caché: ~50% hit rate)
- 1 llamada a análisis de consulta/estructuración (con caché: ~40% hit rate)
- 2-5 llamadas a embeddings para SNOMED (con caché: ~70% hit rate)

**Costo estimado por consulta médica con audio**: ~$0.12-0.18 USD (con optimizaciones)
**Costo estimado por consulta médica escrita** (10-20%): ~$0.08-0.12 USD (sin Speech-to-Text)

---

#### Consultas en Acciones (UniversalQueryAgent)
**Descripción**: Búsquedas y consultas del médico sobre acciones disponibles en el sistema para realizar otras tareas administrativas o de gestión.

**Estimación diaria por médico**:
- **Uso típico**: 2-5 consultas/día (no demasiadas)
- **Uso ocasional**: 1-2 consultas/día
- **Promedio**: ~3 consultas/día

**Cada consulta en acciones genera**:
- 1 llamada a análisis de intención (con caché: ~60% hit rate)
- Posibles llamadas a embeddings para búsqueda semántica

**Costo estimado por consulta en acciones**: ~$0.02-0.04 USD (con optimizaciones)

---

#### Chatbot de Pacientes (Turnos e Historia Clínica)
**Descripción**: Interacciones de pacientes con el chatbot para:
- **Solicitar turnos**: Múltiples mensajes en una conversación para completar la reserva
- **Consultar historia clínica**: Búsquedas y consultas sobre su historial médico
- Otras consultas de pacientes

**Estimación diaria por médico**:
- **Pacientes activos**: 30-60 interacciones/día
- **Alta demanda**: 60-120 interacciones/día
- **Promedio**: ~45 interacciones/día

**Nota**: Los pacientes realizan **varias consultas** durante una conversación para completar tareas (ej: solicitar turno requiere 3-5 mensajes)

**Cada interacción de paciente genera**:
- 1 llamada a análisis de intención (con caché: ~50% hit rate)
- Posibles consultas a base de datos (sin costo de IA)

**Costo estimado por interacción de paciente**: ~$0.01-0.02 USD (con optimizaciones)

---

#### Chat Médico (ConsultaChatController)
**Descripción**: Chat entre médico y sistema durante una consulta en progreso.

**Estimación diaria por médico**:
- **Uso moderado**: 3-5 conversaciones/día
- **Uso intensivo**: 5-10 conversaciones/día
- **Promedio**: ~4 conversaciones/día

**Cada conversación médica genera**:
- 2-5 mensajes con análisis de IA (con caché: ~40% hit rate)

**Costo estimado por conversación médica**: ~$0.05-0.10 USD (con optimizaciones)

---

#### Speech-to-Text (Audio a Texto)
**Descripción**: **PRINCIPAL MÉTODO** - El médico dicta la consulta en audio, que se convierte a texto.

**Estimación diaria por médico**:
- **Mayoría de consultas por audio**: 16-22 transcripciones/día (80-90% de las consultas)
- **Consultas escritas**: 2-4 consultas/día (10-20% de las consultas)
- **Total consultas**: 18-26 consultas/día
- **Promedio**: ~18 transcripciones de audio/día por médico

**Cada transcripción genera**:
- 1 llamada a Speech-to-Text (con caché: ~20% hit rate, audio raramente se repite exactamente)
- Luego pasa por el pipeline normal: corrección → estructuración → SNOMED

**Costo estimado por transcripción de audio**: ~$0.03-0.05 USD (con optimizaciones)

---

### Resumen de Uso Diario por Médico

| Tipo de Consulta/Interacción | Cantidad Diaria | Costo Unitario (USD) | Costo Diario (USD) |
|-------------------------------|-----------------|----------------------|-------------------|
| Consultas médicas con audio (80-90%) | 18 | $0.15 | $2.70 |
| Consultas médicas escritas (10-20%) | 2 | $0.10 | $0.20 |
| Consultas en acciones | 3 | $0.03 | $0.09 |
| Chatbot de pacientes | 45 | $0.015 | $0.68 |
| Chat médico | 4 | $0.075 | $0.30 |
| **TOTAL POR MÉDICO/DÍA** | **72** | - | **~$3.97 USD** |

**Total mensual por médico**: ~**$119.10 USD/mes** (~$119,100 ARS/mes)

**Desglose del flujo completo de consulta médica con audio**:

**Flujo**: Audio → Texto → Formateo/Estructuración → Asociación SNOMED

1. **Speech-to-Text** (audio a texto): 18 × $0.04 = $0.72/día
   - Caché bajo (~20%): cada dictado es único
   
2. **Corrección de texto**: 20 × $0.03 = $0.60/día
   - Caché moderado (~50%): textos similares se reutilizan
   
3. **Análisis/Estructuración** (formateo JSON): 20 × $0.04 = $0.80/día
   - Caché moderado (~40%): estructuras similares se reutilizan
   
4. **Embeddings SNOMED** (asociación a términos): 20 × $0.03 = $0.60/día
   - Caché alto (~70%): términos médicos comunes se reutilizan mucho

5. **Subtotal consultas médicas**: $2.72/día

**Nota**: El Speech-to-Text es el paso más costoso y con menor caché, por lo que representa ~26% del costo total de consultas médicas.

---

## Costo por Cliente (Médico)

### Modelo de Costeo

El costo por médico se calcula basado en:
1. **Costo variable de HuggingFace API**: Proporcional al uso
2. **Costo fijo de infraestructura**: Distribuido entre todos los médicos
3. **Margen de servicio**: Para mantenimiento y soporte

---

### Escenarios de Costo por Médico

#### Escenario 1: Médico Individual (1 médico)
**Costo variable (HuggingFace)**:
- Uso diario: ~$3.97 USD
- Uso mensual: ~$119.10 USD

**Costo fijo (Infraestructura)**:
- VPS/Cloud: $25 USD/mes
- Base de datos: $30 USD/mes
- Otros servicios: $12 USD/mes
- **Total fijo**: $67 USD/mes

**Costo total mensual**: ~$186.10 USD/mes
**Costo por médico**: **~$186.10 USD/mes** (~$186,100 ARS/mes)

---

#### Escenario 2: Clínica Pequeña (5-10 médicos)
**Costo variable (HuggingFace)**:
- Por médico: $119.10 USD/mes
- 7 médicos promedio: $833.70 USD/mes

**Costo fijo (Infraestructura)**:
- VPS/Cloud medio: $25 USD/mes
- Base de datos separada: $30 USD/mes
- Otros servicios: $12 USD/mes
- **Total fijo**: $67 USD/mes

**Costo total mensual**: ~$900.70 USD/mes
**Costo por médico**: **~$128.67 USD/mes** (~$128,670 ARS/mes)
**Ahorro por escala**: ~31% vs médico individual

---

#### Escenario 3: Hospital/Clínica Mediana (20-50 médicos)
**Costo variable (HuggingFace)**:
- Por médico: $119.10 USD/mes
- 35 médicos promedio: $4,168.50 USD/mes

**Costo fijo (Infraestructura)**:
- Cloud con alta disponibilidad: $50 USD/mes
- Base de datos replicada: $60 USD/mes
- CDN: $20 USD/mes
- Otros servicios: $25 USD/mes
- **Total fijo**: $155 USD/mes

**Costo total mensual**: ~$4,323.50 USD/mes
**Costo por médico**: **~$123.53 USD/mes** (~$123,530 ARS/mes)
**Ahorro por escala**: ~34% vs médico individual

---

#### Escenario 4: Red de Salud (100+ médicos)
**Costo variable (HuggingFace)**:
- Por médico: $119.10 USD/mes
- 100 médicos: $11,910 USD/mes

**Costo fijo (Infraestructura)**:
- Infraestructura escalable: $100 USD/mes
- Base de datos replicada: $60 USD/mes
- CDN: $20 USD/mes
- Monitoreo avanzado: $30 USD/mes
- **Total fijo**: $210 USD/mes

**Costo total mensual**: ~$12,120 USD/mes
**Costo por médico**: **~$121.20 USD/mes** (~$121,200 ARS/mes)
**Ahorro por escala**: ~35% vs médico individual

---

### Tabla Comparativa de Costos por Médico

| Cantidad de Médicos | Costo Total Mensual (USD) | Costo por Médico (USD) | Costo por Médico (ARS) |
|---------------------|---------------------------|------------------------|------------------------|
| 1 médico | $186.10 | $186.10 | ~$186,100 |
| 5-10 médicos | $900.70 | $128.67 | ~$128,670 |
| 20-50 médicos | $4,323.50 | $123.53 | ~$123,530 |
| 100+ médicos | $12,120 | $121.20 | ~$121,200 |

---

### Factores que Afectan el Costo por Médico

#### 1. Volumen de Consultas
- **Médico con baja carga** (10 consultas/día, 8 por audio): ~$1.90 USD/día = **$57 USD/mes**
- **Médico promedio** (20 consultas/día, 18 por audio): ~$3.97 USD/día = **$119.10 USD/mes**
- **Médico con alta carga** (40 consultas/día, 36 por audio): ~$7.94 USD/día = **$238.20 USD/mes**

#### 2. Uso de Audio
- **Mayoría por audio** (80-90%): Costo base (ya incluido en estimación)
- **Solo escritas** (raro, 10-20%): -$0.05 USD/día por consulta = **-$1.50 USD/mes** (reducción menor)
- **100% audio** (todos los médicos): Costo estándar

#### 3. Efectividad del Caché
- **Caché efectivo (60% hit rate)**: Reduce costos en ~40%
- **Caché moderado (40% hit rate)**: Reduce costos en ~25%
- **Caché bajo (20% hit rate)**: Reduce costos en ~10%

#### 4. Número de Pacientes Activos
- **Pocos pacientes** (20-30 interacciones/día): -$0.23 USD/día = **-$6.90 USD/mes**
- **Muchos pacientes** (60-120 interacciones/día): +$0.23-0.68 USD/día = **+$6.90-20.40 USD/mes**

---

### Modelo de Precio Sugerido para el Cliente

#### Opción A: Precio Fijo Mensual
**Basado en uso promedio** (considerando mayoría de consultas por audio):
- **Médico individual**: $170-200 USD/mes
- **Clínica pequeña** (5-10 médicos): $120-140 USD/médico/mes
- **Hospital/Clínica mediana** (20-50 médicos): $115-135 USD/médico/mes
- **Red de salud** (100+ médicos): $110-130 USD/médico/mes

**Ventajas**: Predecible para el cliente
**Desventajas**: Puede ser más caro si el uso es bajo

---

#### Opción B: Precio Variable (Pay-per-Use)
**Basado en consultas reales**:
- **Consultas médicas con audio**: $0.15-0.18 USD por consulta (incluye STT + procesamiento)
- **Consultas médicas escritas**: $0.10-0.12 USD por consulta (sin STT)
- **Consultas en acciones**: $0.03-0.05 USD por consulta
- **Interacciones de pacientes**: $0.01-0.02 USD por interacción
- **Transcripciones de audio**: $0.03-0.05 USD por transcripción (si se factura por separado)

**Ventajas**: Justo, solo paga lo que usa
**Desventajas**: Menos predecible para el cliente

---

#### Opción C: Planes Híbridos
**Plan Básico** ($130 USD/médico/mes):
- Hasta 15 consultas médicas/día (12 por audio, 3 escritas)
- Hasta 3 consultas en acciones/día
- Hasta 30 interacciones de pacientes/día
- Exceso: $0.16 USD por consulta médica adicional

**Plan Estándar** ($160 USD/médico/mes):
- Hasta 25 consultas médicas/día (20 por audio, 5 escritas)
- Hasta 5 consultas en acciones/día
- Hasta 60 interacciones de pacientes/día
- Exceso: $0.15 USD por consulta médica adicional

**Plan Premium** ($200 USD/médico/mes):
- Consultas médicas ilimitadas (mayoría por audio)
- Consultas en acciones ilimitadas
- Interacciones de pacientes ilimitadas
- Soporte prioritario

---

### Ejemplo de Cálculo de Costo Real

**Médico con uso típico**:
- 20 consultas médicas/día (18 por audio, 2 escritas) × 30 días = 600 consultas/mes
  - 540 consultas con audio (18/día)
  - 60 consultas escritas (2/día)
- 3 consultas en acciones/día × 30 días = 90 consultas/mes
- 45 interacciones pacientes/día × 30 días = 1,350 interacciones/mes
- 4 conversaciones chat/día × 30 días = 120 conversaciones/mes

**Costo variable**:
- Consultas médicas con audio: 540 × $0.15 = $81 USD
- Consultas médicas escritas: 60 × $0.10 = $6 USD
- Consultas en acciones: 90 × $0.03 = $2.70 USD
- Interacciones pacientes: 1,350 × $0.015 = $20.25 USD
- Chat médico: 120 × $0.075 = $9 USD
- **Subtotal variable**: $118.95 USD

**Costo fijo** (distribuido entre 7 médicos):
- Infraestructura: $67 USD ÷ 7 = $9.57 USD

**Costo total por médico**: ~$128.52 USD/mes

---

## Referencias

- [Documentación de Optimización de Costos HuggingFace](./OPTIMIZACION_COSTOS_HUGGINGFACE.md)
- [HuggingFace Pricing](https://huggingface.co/pricing)
- [AWS Pricing Calculator](https://calculator.aws/)
- [Google Cloud Pricing](https://cloud.google.com/pricing)