# Overview

## Problema

1. El ingreso a guardia permite **alta mínima a mano** (apellido, nombre, documento, fecha, sexo) sin RENAPER/Didit. Eso crea `Persona` con `acredita_identidad = 0` y, si el DNI ya existe, **reusa la fila ignorando** lo tipeado.
2. El registro de la **app paciente** y el alta staff (`registrar-como-staff`) ya exigen identidad validada (DNI PDF417 / Didit KYC = documento + selfie + liveness). Admisión no debe tener un tercer cerebro de padrón.
3. El administrativo **no opera MPI** (no fusiona, no edita domicilio oficial, no inventa documento). Puede teléfono de contacto operativo.
4. Más adelante: actuar *por* el paciente en mostrador (turnos, imprimir) requiere **sesión de ventanilla** (DNI + TTL), no ABM paralelo.

## Fuera de alcance de este plan

- Que admisión atienda, recete o abra HC.
- Configurar agenda/cobertura/PES (AdminEfector / médico).
- Selfie suelta como prueba de identidad de un desconocido (sin DNI ni enrolamiento Didit).
- Lista de espera / derivación administrativa (después de sesión de ventanilla).
