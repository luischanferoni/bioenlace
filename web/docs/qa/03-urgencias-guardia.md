# Guardia y urgencias

[← Índice](./README.md) · Más detalle: [urgencias-guardia.md](../producto/urgencias-guardia.md)

Trabajá con el efector en modo **guardia** ([00-transversal](./00-transversal.md)).

---

## Ver el tablero de guardia

1. **Vos** entrás al inicio de pacientes / tablero de guardia.
2. **El sistema** muestra la cola: quién ingresó, quién espera triage, quién espera médico, quién está siendo atendido.
3. Al refrescar, **se actualiza** sin tener que recargar toda la web a mano.

---

## Registrar triage

1. **Vos** elegís un paciente que aún no tiene triage (o re-triage si corresponde).
2. **Completás** nivel (Manchester 1–5), motivo y signos si los cargás.
3. **El sistema** lo pasa a “espera médico” y registra el evento en el circuito.
4. Si el caso es crítico, **puede** avisar por notificación al equipo según configuración.

---

## Tomar un caso (asignarte)

1. **Vos** (médico) te asignás el caso desde el tablero o el asistente.
2. **El sistema** muestra tu nombre en el caso y **te puede** notificar en el celular.

---

## Empezar a atender

1. **Vos** iniciás la atención desde el tablero.
2. **El sistema** abre la captura clínica de ese ingreso de guardia.
3. **Vos** documentás y guardás (ver [01-captura-clinica.md](./01-captura-clinica.md)).
4. **El sistema** deja el caso en “en atención” mientras corresponda.

---

## Derivar a otro efector

1. **Vos** indicás derivación a otro hospital o servicio.
2. **El sistema** cierra o marca el circuito como derivado y registra el destino.
3. El paciente **sale** de tu cola activa.

---

## Dar de alta / egreso de guardia

1. **Vos** finalizás el episodio de guardia cuando el paciente se va (alta, internación en otro lado, etc.).
2. **El sistema** marca el caso como finalizado y ya no aparece en la cola activa.

---

## Ver cómo va el día (indicadores)

1. **Vos** abrís resumen o indicadores de guardia.
2. **El sistema** muestra tiempos de espera, cantidad por estado, etc., según lo implementado.

---

## Internar desde guardia

1. **Vos** pedís internación para un paciente que está en guardia.
2. **El sistema** te lleva al flujo de ingreso de internación con datos ya cargados.
3. Al confirmar ingreso, **el caso de guardia** se enlaza con la internación (ver [04-internacion.md](./04-internacion.md)).

---

## Emergencia de verdad

1. Si el paciente tiene riesgo de vida (dolor de pecho, no respira, etc.), **no** uses solo esta pantalla: **llamá al 107** o la guardia física.
2. **El sistema** en la app/web también **avisa** que no reemplaza emergencias presenciales.
