# Catálogo de consultas al asistente (paciente)

[← Paciente](./README.md) · Atajos cortos: [asistente.md](./asistente.md) · WhatsApp: [asistente-whatsapp.md](./asistente-whatsapp.md)

Mapa de **todo lo que un paciente podría decirle** al asistente (app o WhatsApp), con **un ejemplo de cada tipo**. Sirve para probar enrutado, degradación y que no se abra el flujo equivocado.

Producto de fondo: [asistente-y-chat.md](../../producto/asistente-y-chat.md), [solicitar-atencion.md](../../producto/solicitar-atencion.md), [apps-paciente-personalsalud.md](../../producto/apps-paciente-personalsalud.md).

No hace falta la frase exacta. Si el tipo está en **Hoy**, algo parecido debería enrutar bien.

---

## Cómo usar este catálogo

1. **Vos** escribís el ejemplo de la columna (o una variante). Si hay 1. y 2., son dos envíos: primero el 1, después el 2.
2. **El sistema** clasifica: charla clínica, flujo operativo o menú/ayuda.
3. **Vos** marcás si abrió el camino correcto y si **no** prometió lo que no puede hacer (diagnóstico, receta, teléfono inventado, turno en urgencia).

Tres puertas cubren la mayoría de la vida del paciente:

```mermaid
flowchart LR
  P[Mensaje del paciente]
  P --> A["Me pasa algo"]
  P --> B["Trámite de agenda"]
  P --> C["Ver lo mío"]
  A --> SA[Solicitar Atención o charla + botón]
  B --> TU[Intents de turnos]
  C --> MI[Atenciones, recetas, lab, planes]
```

| Puerta | Qué busca el paciente | Camino típico |
|--------|------------------------|---------------|
| Me pasa algo | Síntoma, estudio, control, urgencia | Charla + atajo **Solicitar Atención** (`atencion.necesito-atencion`) |
| Trámite de agenda | Sacar / ver / cancelar / mover / confirmar | Intents `turnos.*` |
| Ver lo mío | Resumen, recetas, lab, recordatorios, representación | Intents de lectura / hub nativo |

---

## Leyenda de cobertura

| Marca | Significado al probar |
|-------|------------------------|
| **Hoy** | El asistente debe enrutar o guiar. Fallo de smoke si abre otro flujo o se queda mudo. |
| **Pantalla** | Existe en inicio, configuración o push. El chat puede abrir la pantalla, degradar a la app o no tener frase propia. |
| **Fuera** | El paciente lo pregunta igual. **No** diagnosticar, recetar ni inventar datos del centro. Mensaje claro o invitación a Solicitar Atención. |
| **Futuro** | Dirección de producto. No fallar el smoke si no existe; anotar si aparece. |

El asistente **no confirma diagnósticos**, **no receta** y **no inventa** médicos, teléfonos ni direcciones. WhatsApp es el mismo asistente, solo si el paciente **escribe primero** (avisos proactivos siguen en push).

Hacia dónde va el producto (agentes, educación post-consulta, preguntas dinámicas): [ideas-a-futuro](../../producto/ideas-a-futuro/README.md), [agentes-autonomos.md](../../producto/agentes-autonomos.md).

---

## 1. Me siento mal / orientación (sin pedir turno todavía)

Canal **conversacional**: empatía breve, orientación prudente y botón **Solicitar Atención**. No abrir reserva de agenda si no pidió turno.

| Tipo | Ejemplo (pegar / enviar) | Cobertura | Qué deberías ver |
|------|--------------------------|-----------|------------------|
| Síntoma suelto | *«Me duele la cabeza desde anoche»* | **Hoy** | Charla + oferta Solicitar Atención |
| Síntoma + parte del cuerpo | *«Tengo un pinchazo en el pecho cuando respiro»* | **Hoy** | Igual; no diagnóstico |
| Varios síntomas juntos | *«Tengo fiebre, tos y me duele el cuerpo»* | **Hoy** | Charla + oferta |
| Lesión / traumatismo | *«Me caí de la bici y se me hinchó el tobillo»* | **Hoy** | Charla + Solicitar Atención |
| Salud mental | *«Estoy muy ansioso y no puedo dormir»* | **Hoy** | Empatía + oferta; no es queja de la app |
| Pediatría (tutor) | *«Mi nene de 3 años tiene 39 de fiebre»* | **Hoy** | Charla; turno del menor exige representación activa |
| Embarazo | *«Estoy de 20 semanas y sangro un poco»* | **Hoy** | Prudencia; puede ser urgencia |
| Post-operatorio | *«Me operaron el viernes y me arde la herida»* | **Hoy** | Oferta de atención; no interpreta la cirugía |
| Efecto de un medicamento | *«Arranqué el enalapril y me mareo»* | **Hoy** | No ajusta la dosis solo; Control/Seguimiento o atención |
| Alarma clásica | *«Me falta el aire y me suda el pecho»* | **Hoy** | Urgencia / 107; **no** reserva ambulatoria |

**1.** enviá · **2.** enviá (no en el mismo mensaje; no toques el botón entre medio). Oferta en el 2; la de arriba se apaga.

| Tipo | Ejemplo (pegar / enviar) | Cobertura | Qué deberías ver |
|------|--------------------------|-----------|------------------|
| Evolución | 1. *«Tengo fiebre, tos y me duele el cuerpo»*<br>2. *«Empezó ayer y se me fue poniendo peor»* | **Hoy** | Sigue la charla sin repetir; oferta en el 2 |
| Comparar con algo conocido | 1. *«Me duele la panza»*<br>2. *«Es como la gastritis que tuve el año pasado»* | **Hoy** | Orientación; no confirma el diagnóstico viejo |
| Pedir qué hacer | 1. *«Tengo fiebre, tos y me duele el cuerpo»*<br>2. *«¿Qué hago con esto?»* | **Hoy** | Orientación + oferta de atención |
| Pedir a qué servicio ir | 1. *«Me caí de la bici y se me hinchó el tobillo»*<br>2. *«¿Esto es de clínica o de traumatología?»* | **Hoy** | Sugiere tipo de servicio; no nombra un médico inventado |
| Pedir si es urgente | 1. *«Tengo un pinchazo en el pecho cuando respiro»*<br>2. *«¿Esto es para guardia o puedo esperar?»* | **Hoy** | Si hay alarma, camino urgencia; si no, orientación |
| Pedir si puede esperar al turno | 1. *«Me duele la cabeza desde anoche»*<br>2. *«¿Puedo aguantar hasta el jueves?»* | **Hoy** | No da alta médica; invita a atención |
| Pedir automedicación | 1. *«Me duele la cabeza desde anoche»*<br>2. *«¿Puedo tomar ibuprofeno?»* | **Fuera** | No receta; sugiere consultar |
| Pedir diagnóstico | 1. *«Tengo un pinchazo en el pecho cuando respiro»*<br>2. *«¿Será un infarto?»* | **Fuera** | No diagnostica; si hay alarma, urgencia / 107 |

Detalle del árbol de alarmas: [triage-reserva-turno.md](../../producto/triage-reserva-turno.md).

---

## 2. Pedir atención (puerta principal)

Atajo **Solicitar Atención** (`atencion.necesito-atencion`): malestar nuevo, estudio o práctica, control/seguimiento, urgencia.

*«Quiero un turno»* / *«sacar turno»* sin motivo clínico → `turnos.crear-como-paciente` (agenda pura). Pasos: [turnos.md](./turnos.md).

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Malestar nuevo, genérico | *«Necesito atención, me siento mal»* | **Hoy** | Solicitar Atención → malestar |
| Malestar + zona | *«Me duele la panza del lado derecho»* | **Hoy** | Malestar → zona / detalle |
| Estudio / práctica | *«Necesito una ecografía»* / *«turno para mamografía»* / *«kinesio»* | **Hoy** | Camino estudio o práctica (acto → oferta del centro) |
| Laboratorio como acto | *«Quiero sacar turno para análisis de sangre»* | **Hoy** | Estudio/práctica, no el listado de resultados |
| Control / seguimiento | *«Tengo que controlar la diabetes»* | **Hoy** | Hub Control/Seguimiento |
| Control por edad/sexo | *«¿Me toca alguna vacuna?»* / *«control ginecológico»* | **Hoy** | Hub: control recomendado si aplica |
| Urgencia declarada | *«Es una urgencia, ¿adónde voy?»* | **Hoy** | Categoría de alarma; banda A sin reserva en app |
| 107 / guardia | *«¿Llamo al 107 o voy a la guardia del centro?»* | **Hoy** | Derivación; no cupo ambulatorio |
| Ver un médico (vago) | *«Quiero ver a un médico»* | **Hoy** | Solicitar Atención o charla + botón |
| Pedir el de siempre | *«Quiero turno con la doctora Pérez»* | **Hoy** | Agenda; el profesional aparece si hay PES y cupo |
| Pedir un servicio del centro | *«Turno en odontología»* | **Hoy** | Elige oferta del **centro**, no “especialidad” suelta |
| Pedir “el mío” por oferta | *«Solicita un turno para mi dentista»* | **Hoy** | `turnos.crear-como-paciente` (no charla empática). Cruza la mención con la oferta; si “dentista” no matchea el nombre, elegís el servicio. **No** confirma el turno solo |
| Pedir un especialista | *«Necesito un cardiólogo»* | **Hoy** | Hub de medicina clínica; especialista suele pedir derivación vigente |
| Centro más cerca | *«El más cercano a mi casa»* | **Hoy** | Mapa / centros cercanos en el flujo de reserva |
| Centro concreto | *«En CIS Banda»* | **Hoy** | Filtra efector si está en contexto |
| Preferencia de día/hora | *«La semana que viene a la tarde»* | **Hoy** | Lo usa al elegir slot; no reserva a ciegas |
| Solo sacar turno | *«Quiero un turno»* | **Hoy** | `turnos.crear-como-paciente` |
| Teleconsulta | *«¿Puedo hacerlo por videollamada?»* | **Hoy** | Paso modalidad solo si triage y servicio lo permiten |
| Consulta por mensaje | *«No puedo ir, ¿le puedo escribir al médico?»* | **Hoy** | Control/Seguimiento o modalidad mensaje; no promete respuesta inmediata |
| Presencial sí o sí | *«Tiene que ser presencial, no quiero video»* | **Hoy** | Fija presencial |
| No hay cupo | *«No hay turnos, ¿qué hago?»* | **Hoy** | Mensaje claro; no inventa horarios |
| Lista de espera | *«Avisame cuando haya un hueco»* | **Futuro** | Hoy puede no existir; no fallar el smoke |

Contexto de provincia/sector: [contexto-registro.md](./contexto-registro.md). Teleconsulta: [teleconsulta-elegibilidad.md](../../producto/teleconsulta-elegibilidad.md). Hub reserva: [medicina-clinica-hub-reserva.md](../../producto/medicina-clinica-hub-reserva.md).

---

## 3. Citas que ya tengo

Flujos: [turnos.md](./turnos.md).

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Listar próximos | *«¿Qué turnos tengo?»* | **Hoy** | `turnos.ver-mis-turnos-como-paciente` (no el historial) |
| Detalle de uno | *«¿A qué hora es el de mañana?»* | **Hoy** | Lista o detalle del pendiente |
| Con quién / dónde | *«¿Con quién es y en qué consultorio?»* | **Pantalla** | Datos del turno si están; no inventar consultorio |
| Cómo llegar | *«¿Dónde queda el centro?»* | **Pantalla** / **Fuera** | No inventar dirección si no está en contexto |
| Qué llevar | *«¿Tengo que llevar estudios?»* | **Pantalla** / **Fuera** | Puede vivir en motivos/pre-consulta; no inventar preparación |
| Cancelar | *«Cancelá el turno del martes»* | **Hoy** | `turnos.cancelar-como-paciente-flow` |
| Cancelar “el de…” | *«Cancelá el de odontología»* | **Hoy** | Elige entre pendientes |
| Reprogramar | *«Pasame el turno al jueves»* | **Hoy** | `turnos.modificar-como-paciente-flow` |
| Confirmar que voy | *«Confirmo que voy mañana»* | **Hoy** | `turnos.confirmar-asistencia-flow` |
| No voy a llegar | *«Voy a llegar tarde, ¿me esperan?»* | **Fuera** | No promete que el profesional espera |
| Política de plazos | *«¿Hasta cuándo puedo cancelar?»* | **Hoy** | `turnos.consultar-politica-autogestion-flow` |
| Reubicar (agenda rota) | *«Me cancelaron el turno, quiero otro horario»* | **Hoy** | `turnos.reubicar-como-paciente-flow` si está en resolución |
| Aceptar adelanto | *«Sí, acepto el turno de mañana a las 10»* | **Pantalla** | Suele venir de **push** `TURNO_ADVANCE_OFFER` |
| Rechazar adelanto | *«No, me quedo con el mío»* | **Pantalla** | El turno original sigue |
| Historial de citas | *«Mostrame los turnos que ya tuve»* | **Hoy** | `turnos.ver-turnos-anteriores-como-paciente` (no los próximos) |
| Última vez en una oferta | *«Decime cuándo fue la última vez que fui al dentista»* | **Hoy** | `turnos.ver-ultimo-en-oferta-como-paciente` (odontología es un ejemplo). **No** usa el extracto de HC ni `atencion.ver-ultima-como-paciente` |
| Turno duplicado / conflicto | *«Tengo dos turnos el mismo día»* | **Pantalla** | Resolución desde inicio / push |
| Recordatorio | *«Avisame una hora antes»* | **Pantalla** | Preferencias / push; el chat puede no configurar el aviso |

Adelantamiento por cancelación: [turnos.md](../../producto/turnos.md) (agente A03).

---

## 4. Antes de la consulta (preparar el encuentro)

Journey: [recorrido-pre-post-consulta.md](../../producto/recorrido-pre-post-consulta.md). Pasos de prueba: [turnos.md](./turnos.md) (§ Preparar la consulta).

El chat de **motivos** no es el mismo hilo que el asistente general. El cuestionario pre-consulta (`care-packs.asistencia-pre-consulta-flow`) existe; el atajo está oculto.

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Contar el motivo | *«Voy por el dolor de rodilla que no se me pasa»* | **Pantalla** | Chat de motivos en la ventana (~4 h antes) |
| Completar intake | *«Las preguntas de antes de la consulta»* | **Pantalla** | Formulario previo al chat de motivos |
| Cuestionario de cohorte | *«Las preguntas del control de hipertensión»* | **Pantalla** | Pack de asistencia si hay cohorte |
| Audio de motivos | *«Te mando un audio de lo que me pasa»* | **Pantalla** | Se guarda en el hilo de motivos |
| Ya lo dije | *«¿El médico va a ver lo que escribí?»* | **Hoy** / **Pantalla** | Sí: el equipo lo ve en historia clínica |
| Ventana cerrada | *«No me deja cargar motivos»* | **Hoy** | Mensaje de fuera de ventana; no error crudo |
| Preparación | *«¿Tengo que ir en ayunas?»* | **Fuera** | No inventar indicación; puede estar en el pack |

---

## 5. Durante / alrededor del encuentro

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Estoy en la sala | *«Ya llegué, ¿me van a llamar?»* | **Fuera** | No opera la fila de admisión |
| Videollamada | *«¿Cómo entro a la videollamada?»* | **Pantalla** | Enlace / pantalla del turno remoto |
| No me atiende | *«Estoy en la llamada y no entra nadie»* | **Pantalla** / **Fuera** | No inventar estado del profesional |
| Consulta por mensaje: estado | *«¿Ya leyeron lo que mandé?»* | **Pantalla** | Card de consultas por mensaje en inicio |
| Consulta por mensaje: agregar dato | *«Se me olvidó decir que tomo enalapril»* | **Pantalla** | Sigue el hilo async si está abierto |
| Guardia (familiar) | *«Mi mamá está en guardia, ¿cómo va?»* | **Fuera** | Tablero EMER es del personal, no del paciente |

Consulta por mensaje: [consultas-seguimiento.md](../../producto/consultas-seguimiento.md).

---

## 6. Después: qué me dijeron

Pasos: [laboratorio-receta-planes.md](./laboratorio-receta-planes.md). Producto: [resumen-atencion-paciente.md](../../producto/resumen-atencion-paciente.md).

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Última atención | *«¿Qué me dijo el médico ayer?»* | **Hoy** | `atencion.ver-ultima-como-paciente` o detalle del resumen |
| Historial de atenciones | *«Mis consultas anteriores»* | **Hoy** | Atajo **Mis atenciones** (`atencion.mis-atenciones-como-paciente`) |
| Indicaciones | *«¿Qué indicaciones me dejaron?»* | **Hoy** | En el resumen publicado |
| Diagnóstico en claro | *«¿Qué significa lo que me pusieron?»* | **Fuera** | El resumen puede estar en lenguaje claro; el chat no diagnostica de nuevo |
| Pedidos | *«¿Qué estudios me pidieron?»* | **Hoy** | Enlaces en el detalle de la atención |
| Resumen aún no listo | *«Todavía no veo el resumen»* | **Hoy** | Mensaje de que aún no se publicó (cola post-cierre) |
| Cómo evolucionar | *«¿Cuándo tengo que volver si no mejoro?»* | **Futuro** | Educación / touchpoint; hoy puede degradar a Solicitar Atención |
| Certificado / constancia | *«Necesito el certificado para el trabajo»* | **Fuera** | No hay emisión desde el chat paciente |
| Epicrisis / internación | *«Me dieron el alta, ¿dónde está el informe?»* | **Fuera** | Expediente amplio es del personal |

---

## 7. Recetas y medicación

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Ver recetas | *«Mostrame mis recetas»* | **Hoy** | `receta.ver-recetas-como-paciente` |
| PDF / farmacia | *«Mandame el PDF para la farmacia»* | **Hoy** | Detalle + descarga PDF |
| Código de verificación | *«¿Cuál es el código de la receta?»* | **Pantalla** | En el detalle de la receta emitida |
| Renovar | *«Se me está por terminar el enalapril»* | **Hoy** | Solicitar Atención → Control/Seguimiento (no listado de recetas) |
| Repetir | *«Repetíme la receta de siempre»* | **Hoy** | Mismo hub; consulta por mensaje |
| Ajuste | *«La metformina me cae mal, ¿me la pueden cambiar?»* | **Hoy** | Hub: solicitar ajuste |
| Dosis / horario | *«¿A qué hora tomo la pastilla de la noche?»* | **Pantalla** | Plan / recordatorios; el chat no cambia la indicación |
| Interacción | *«¿Puedo tomar esto con alcohol?»* | **Fuera** | No receta ni da indicación farmacológica |
| Receta vencida | *«Esta receta ya venció»* | **Hoy** | Visible en listado; renovar por seguimiento |
| Anulada | *«El médico me dijo que la anularon»* | **Hoy** | Estado en listado; no re-emite el chat |
| Stock / farmacia del hospital | *«¿Lo tienen en la farmacia del centro?»* | **Fuera** | Dispensación no es canal paciente |

Renovación y ajuste: [consultas-seguimiento.md](../../producto/consultas-seguimiento.md). Receta: [receta-electronica.md](../../producto/receta-electronica.md).

---

## 8. Laboratorio e informes

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Listar | *«Mis análisis»* / *«resultados de sangre»* | **Hoy** | `laboratorio.ver-resultados-como-paciente` |
| El último | *«¿Ya salió el de ayer?»* | **Hoy** | Lista; vacío claro si aún no ingresó |
| Un analito | *«¿Cómo está el colesterol?»* | **Pantalla** | Detalle del informe; el chat no interpreta |
| Interpretar | *«¿Está alto el TSH?»* | **Fuera** | Push puede decir normal/control/crítico; no explica clínica |
| PDF | *«Descargame el PDF del laboratorio»* | **Hoy** | Descarga desde el detalle |
| Imagen (RX, eco) | *«¿Dónde veo la placa?»* | **Fuera** | El listado paciente es informes de laboratorio |
| Pedido pendiente | *«Todavía no me hice los análisis que me pidieron»* | **Pantalla** | Pedido en el resumen de atención |
| Dónde hacérmelos | *«¿Dónde puedo hacerme la sangre?»* | **Fuera** | No inventar laboratorio externo |

Ingesta y avisos: [laboratorio.md](../../producto/laboratorio.md).

---

## 9. Tratamiento, condiciones y preventivos

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Planes activos | *«¿Qué tratamientos tengo?»* | **Pantalla** | Card / detalle de plan en inicio |
| Recordatorios | *«Activá las alarmas de las pastillas»* | **Hoy** | `tratamiento.recordatorios-como-paciente` |
| Marcar adherencia | *«Ya tomé la de las 8»* | **Pantalla** | Si el plan lo permite en la app |
| Duda del plan | *«No entiendo para qué es esta pastilla»* | **Hoy** | Control/Seguimiento (consulta por mensaje), no receta |
| Evolución | *«Cuento cómo me fue con el tratamiento»* | **Hoy** | Hub: consulta o evolución |
| Condición del inicio | *«Abrí lo de mi hipertensión»* | **Pantalla** | Card **Tus condiciones** → acciones del hub |
| Control recomendado | *«Me aparece una vacuna, ¿qué hago?»* | **Hoy** | Hub: control recomendado (no es indicación médica firme) |
| Post-consulta programado | *«Me llegó ‘¿cómo evolucionó?’»* | **Pantalla** / **Futuro** | Touchpoint de cohorte si hay pack |
| Educación | *«Explicame mi diabetes en simple»* | **Futuro** | No exigir módulo educativo en smoke |

Planes: [planes-de-tratamiento.md](../../producto/planes-de-tratamiento.md). Protocolos del hub: [solicitar-atencion.md](../../producto/solicitar-atencion.md).

---

## 10. Familia y operar por otro

Producto: [representacion-paciente.md](../../producto/representacion-paciente.md). Chip **A cargo de** en inicio.

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Tutela | *«Quiero vincular a mi hijo»* | **Hoy** | `personas.vincular-menor-flow` (queda pendiente hasta el centro) |
| Turno del menor | *«Sacá turno para mi hija de 8»* | **Hoy** | Con sujeto activo; si no hay tutela, no opera por el menor |
| Delegar | *«Que mi hija gestione mis turnos»* | **Hoy** | `personas.designar-representante-flow` |
| Quién opera por mí | *«¿Quién puede sacar turnos a mi nombre?»* | **Pantalla** | Hub de representación |
| Aviso N9 | *«Avisame si alguien actúa por mí»* | **Pantalla** | Configuración de alertas |
| Revocar | *«Sacá a mi hermano de representantes»* | **Pantalla** | Hub de representación |

No confundir tutela (menor sin cuenta, verifica el staff) con delegación (otro adulto con cuenta, activa al instante).

---

## 11. Cuenta, identidad y app

Sesión: [sesion-paciente-app.md](../../producto/sesion-paciente-app.md). Registro: [contexto-registro.md](./contexto-registro.md).

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Registro | *«No me deja crear la cuenta»* | **Pantalla** | Flujo de alta / Didit; no es un intent clínico |
| Didit / selfie | *«No me reconoce la cara»* | **Pantalla** | Reingreso biométrico |
| Olvidé cómo entrar | *«Cerré sesión y no puedo entrar»* | **Pantalla** | Login + Didit |
| Huella del teléfono | *«¿Por qué me pide la huella cada rato?»* | **Pantalla** | Bloqueo local por inactividad |
| Datos personales | *«Cambiar mi domicilio»* | **Pantalla** | Configuración; no el chat de turnos |
| Provincia / sector | *«Me piden provincia y no sé qué poner»* | **Pantalla** | Contexto; sin provincia no avanza el turno |
| Notificaciones | *«No me llegan los avisos»* | **Pantalla** | Permiso del sistema + preferencias |
| WhatsApp | *«¿Puedo hablarte por WhatsApp?»* / *«hola»* en WA | **Hoy** | Vinculación SI/NO; luego los mismos atajos |
| Preferencias | *«No me mandes tantos recordatorios»* | **Pantalla** | Recordatorios de tratamiento / alertas |

Smoke WhatsApp: [asistente-whatsapp.md](./asistente-whatsapp.md).

---

## 12. Centro, red y el sistema

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Ministerio / provincia | *«Ministerio de salud de mi provincia»* | **Hoy** | `paciente-contexto.recurso-provincial-como-paciente-flow` |
| Teléfono del centro | *«¿Cuál es el teléfono de admisión?»* | **Fuera** | No inventar teléfono |
| Horario de guardia | *«¿Hasta qué hora atiende la guardia?»* | **Fuera** | No inventar horario |
| Cobertura / obra social | *«¿Atienden con PAMI?»* | **Fuera** | Facturación/cobertura no es canal paciente |
| Turno de otro efector | *«En el hospital de la capital»* | **Hoy** | Solo centros del contexto (provincia/sector) |
| Profesionales del centro | *«¿Qué médicos hay?»* | **Pantalla** | Aparecen al reservar; no un listado staff |

---

## 13. Plataforma, ayuda y meta

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Saludo | *«Hola»* / *«gracias»* | **Hoy** | Charla breve; puede ofrecer menú o atajos |
| Menú | *«¿Qué puedo hacer?»* | **Hoy** | Atajos / opciones (informational) |
| Queja de la app | *«La app se cuelga al sacar turno»* | **Hoy** | `plataforma.enviar-queja-como-paciente-flow` |
| Queja de atención | *«Me hicieron esperar dos horas»* | **Hoy** | Misma queja operativa; **no** Solicitar Atención |
| Sugerencia | *«Deberían avisar si el médico se atrasa»* | **Hoy** | Queja / sugerencia |
| Facturación | *«¿Cuánto me van a cobrar?»* | **Fuera** | No cotiza |
| Privacidad | *«¿Quién ve mis datos?»* | **Fuera** | No improvisar política legal en el chat |

La queja **no** es para síntomas ni urgencias.

---

## 14. Frases sucias (las más reales)

Un mismo pedido llega de diez maneras. Probar al menos una variante “sucia” por puerta.

| Variante | Ejemplo | Cobertura | Qué deberías ver |
|----------|---------|-----------|------------------|
| Ortografía / abreviación | *«kiero tno c la perez»* | **Hoy** | Reserva o desambiguación; no error |
| Audio / voz | *«[audio] me duele acá…»* | **Pantalla** | Motivos sí; WhatsApp media aún fuera de smoke |
| Foto | *«Te mando foto de la herida»* | **Futuro** / **Pantalla** | Motivos pueden aceptar foto; WA media no es smoke |
| Mezcla síntoma + turno | *«Me duele la espalda, sacame un turno para el lunes»* | **Hoy** | Pedido explícito de turno → operativo; si solo síntoma, charla primero |
| Mezcla receta + urgencia | *«Se me acabó la insulina y estoy mal»* | **Hoy** | Priorizar malestar/urgencia, no solo renovar |
| Por un tercero sin representación | *«Sacá turno para mi vieja»* | **Hoy** | No opera por otra persona sin delegación |
| Cambio de tema a mitad | *«Cancelá el de mañana. Ah, y ¿salieron los análisis?»* | **Hoy** | Un flujo a la vez; el segundo puede quedar para el siguiente mensaje |
| Enfado | *«Esto es un desastre, cancelen todo»* | **Hoy** | Cancelar o queja; no abrir malestar clínico |
| Confirmación corta | *«sí»* / *«el de las 10»* | **Hoy** | Solo tiene sentido **dentro** de un flow ya abierto |

---

## 15. Datos personales, pasado y “hacelo vos”

El asistente **interpreta** y abre un flow o una lista; **no** actúa en silencio con tu historia ni completa trámites sin que confirmes. Tesis: [asistente-y-chat.md](../../producto/asistente-y-chat.md). Odontología / dentista es **habla**, no un intent propio: se cruza con la **oferta del centro** (servicio institucional), no con una especialidad suelta.

### Menciones de lo mío o del pasado

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Última vez en una oferta | *«Decime cuándo fue la última vez que fui al dentista»* | **Hoy** | `turnos.ver-ultimo-en-oferta-como-paciente`. Si cruza la oferta, fecha + profesional; si “dentista” no matchea el nombre (p. ej. el servicio se llama Odontología), lista corta de turnos pasados para que ubiques la línea. **No** inventa la fecha |
| Misma idea, otra oferta | *«¿Cuándo fui a kinesio?»* | **Hoy** | El mismo intent; kinesio / cardiología / etc. |
| Nombre de la oferta en el centro | *«Última cita en odontología»* | **Hoy** | Más fácil de cruzar que el coloquial “dentista” |
| Última atención (resumen clínico) | *«¿Qué me dijo el médico ayer?»* | **Hoy** | `atencion.ver-ultima-como-paciente` — no es “última vez al dentista” |
| Alergia / condición en charla de síntoma | *«Me duele la cabeza, ¿puedo tomar ibuprofeno?»* | **Hoy** | Charla prudente; **no** receta. El extracto de HC (si está activo) solo evita contradecir alergias; no responde historial de turnos |
| Dato que no está en turnos ni HC acotada | *«¿Cuánto medía mi hijo en el último control?»* | **Fuera** | No hay intent; no inventar. Puede degradar a atenciones o a “no lo tengo acá” |
| Pedir “el de siempre” por nombre | *«Quiero turno con la doctora Pérez»* | **Hoy** | Reserva; el PES aparece si hay cupo |
| Pedir “el mío” por oferta | *«Turno para mi dentista»* / *«con mi kinesiólogo»* | **Hoy** | Reserva; hint de oferta o de profesional. Si no cruza, elegís servicio/profesional. No reserva a ciegas |

### Que el asistente lo haga por vos

| Tipo | Ejemplo | Cobertura | Qué deberías ver |
|------|---------|-----------|------------------|
| Sacar el turno vos | *«Sacame el turno, no me preguntes»* | **Hoy** | Abre `turnos.crear-como-paciente` o Solicitar Atención; **sigue** pidiendo servicio/centro/horario. No confirma solo |
| Elegí el más cercano y listo | *«Agendá en el más cercano a las 10»* | **Hoy** | Puede abrir mapa / slots; **vos** confirmás el cupo |
| Cancelá todos | *«Cancelá todos mis turnos»* | **Hoy** / **Pantalla** | Cancelación de **uno** (o lista para elegir). No borra la agenda entera en un paso silencioso |
| Hablá con el médico por mí | *«Decile al doctor que me duele»* | **Fuera** | No envía mensajes al profesional. Consulta por mensaje / Solicitar Atención si aplica |
| Completá los motivos vos | *«Inventá el motivo de la consulta»* | **Fuera** | Motivos los escribe el paciente en su ventana |
| Usá toda mi HC y contestá | *«Leé mi historia y decime qué tengo»* | **Fuera** | No diagnostica. Extracto acotado solo en charla de síntomas (opt-out en Configuración) |

Si el flow se siente largo, lo correcto es que **hidrate** lo que ya dijiste (oferta, “el mío”), no que Gemini cierre el trámite.

---

## No confundir (enrutado)

Si el mensaje mezcla temas, gana la **acción explícita** (cancelar, sacar turno, ver recetas) salvo alarma / síntoma agudo.

| Dijo algo como… | No abrir | Abrir / hacer |
|-----------------|----------|----------------|
| *«Me duele…»* sin pedir turno | `turnos.crear-como-paciente` | Charla + **Solicitar Atención** |
| *«Quiero un turno»* sin síntoma | Triage largo de malestar | `turnos.crear-como-paciente` |
| *«Me falta el aire»* | Reserva ambulatoria | Urgencia / 107 |
| *«Renovar el enalapril»* | Listado de recetas | **Solicitar Atención** → Control/Seguimiento |
| *«Mis análisis»* | Turno para laboratorio | Resultados |
| *«Turno para análisis»* | Listado de resultados | Estudio o práctica |
| *«Mis turnos»* | Historial pasado | Próximos |
| *«Turnos que ya tuve»* | Próximos | Anteriores |
| *«La app se cuelga»* | Solicitar Atención | Queja |
| *«¿Puedo tomar ibuprofeno?»* | Receta o ajuste de plan | Charla prudente; no receta |
| *«Vincular a mi hijo»* | Delegar representante | Tutela de menor |
| *«Que mi hija gestione mis turnos»* | Tutela | Designar representante |
| *«Sacá turno para mi vieja»* sin delegación | Turno de un tercero | Explicar representación |
| *«Solicita un turno para mi dentista»* | Charla empática / Solicitar Atención por síntoma | `turnos.crear-como-paciente` (oferta del centro) |
| *«Última vez que fui al dentista»* | Extracto de HC / última atención genérica | `turnos.ver-ultimo-en-oferta-como-paciente` |
| *«Sacalo vos y confirmá»* | Reserva cerrada sin pantallas | Mismo flow; **vos** elegís y confirmás |

---

## Relación con el resto

| Documento | Uso |
|-----------|-----|
| [asistente.md](./asistente.md) | Atajos y frases mínimas para smoke diario |
| [asistente-whatsapp.md](./asistente-whatsapp.md) | Canal WhatsApp (mismo asistente, solo reactivo) |
| [turnos.md](./turnos.md) | Pasos de reserva, cancelar, motivos |
| [laboratorio-receta-planes.md](./laboratorio-receta-planes.md) | Resultados, recetas, tratamientos, resúmenes |
| [checklist.md](./checklist.md) | Casos AST a marcar |
| [solicitar-atencion.md](../../producto/solicitar-atencion.md) | Puerta malestar / estudio / seguimiento / urgencia |
| [asistente-y-chat.md](../../producto/asistente-y-chat.md) | Cómo conversa Bioenlace |
