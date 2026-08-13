# Guardia y urgencias

[← Staff](./README.md) · Más detalle: [urgencias-guardia.md](../../producto/urgencias-guardia.md)

Trabajá con el efector en modo **guardia** ([transversal.md](./transversal.md)).

---

## Ingresar un paciente a guardia

En la **web** podés ingresar un paciente **que ya está en el sistema** o como **NN**. Si no está en el sistema, la leyenda indica usar la **app Personal de Salud** para escanear el DNI.

1. **Vos** (rol **Administrativo**), con sesión de **guardia**, pulsá **Ingresar paciente**.
2. **Buscás** por apellido o documento y elegís al paciente. Si no está: en web usá **Sin documento / NN** o la app para DNI; en la app también podés **identificar con DNI** (código de barras / documento+sexo) o **foto Didit**. No se inventa DNI. Cuando aparece el documento, **Identificar** vincula el episodio (web: paciente conocido; app: también DNI).
3. **Completás** cómo llega (camina / silla / camilla), con quién, y opcionalmente cobertura y situación.
4. **El sistema** lo deja en espera de triage.

En la demo sandbox: perfil **Administrativo demo**; los pacientes de prueba se buscan por apellido (Alonso, Benitez, Castro…). El que ya está en cola no aparece.

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

1. **Vos** (médico) iniciás la atención desde el tablero (**Atender**).
2. **El sistema** abre la captura clínica de ese ingreso de guardia.
3. **Vos** documentás y guardás (ver [medico/captura-clinica.md](../medico/captura-clinica.md)).
4. **El sistema** deja el caso en “en atención” mientras corresponda.

Enfermería **no** usa Atender (`iniciar-atencion`): después del triage abre **Nota** (misma HC, sin tomar el caso).

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
2. **El sistema** muestra tiempos de espera, cantidad por estado, etc., según lo que tenga configurado el efector.

---

## Internar desde guardia

1. **Vos** pedís internación para un paciente que está en guardia.
2. **El sistema** te lleva al flujo de ingreso de internación con datos ya cargados.
3. Al confirmar ingreso, **el caso de guardia** se enlaza con la internación (ver [internacion.md](./internacion.md)).

---

## Emergencia de verdad

1. Si el paciente tiene riesgo de vida (dolor de pecho, no respira, etc.), **no** uses solo esta pantalla: **llamá al 107** o la guardia física.
2. **El sistema** en la app/web también **avisa** que no reemplaza emergencias presenciales.
