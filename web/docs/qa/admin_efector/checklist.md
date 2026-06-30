# Checklist — Admin efector (web frontend)

[← Admin efector](./README.md) · [Índice general](../README.md)

**Leyenda:** 🔴 bloqueante · 🟡 importante · 🟢 regresión

---

## Gestión del efector (AEF)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| AEF-01 | 🔴 | Login AdminEfector | Web frontend OK |
| AEF-02 | 🟡 | Elegir efector | Contexto OK |
| AEF-04 | 🔴 | Editar datos efector | Guarda OK |
| AEF-06 | 🔴 | Listar servicios del efector | Listado correcto |
| AEF-07 | 🟡 | Crear servicio | Alta OK |
| AEF-10 | 🔴 | Listar PES | Asignaciones visibles |
| AEF-11 | 🟡 | Alta PES | Profesional en servicio |
| AEF-12 | 🟡 | Configurar agenda PES | Cupos en turnos |
| AEF-14 | 🟡 | Crear usuario efector (primera vez) | Login web y app OK |
| AEF-14b | 🟢 | Vincular usuario existente a otro efector | Mismo login |
| AEF-15 | 🟡 | Asignar rol operativo | Menús del rol |
| AEF-17 | 🟢 | Licencia profesional | Registro OK |
| AEF-19 | 🟢 | Infraestructura cama | Mapa internación |
| AEF-21 | 🟡 | Indicadores agenda | Sin error |
| AEF-22 | 🟢 | Planilla reporte | Coherente |
| AEF-23 | 🟡 | Turno paciente tras config | Turno en servicio |
| AEF-24 | 🟡 | Login app Personal de Salud (usuario AEF-14) | Wizard + inicio OK |
| AEF-25 | 🟢 | Mismo usuario web + app | Panel inicio alineado |

→ [gestion-efector.md](./gestion-efector.md)

Contexto paciente app: [paciente/checklist.md](../paciente/checklist.md) (CTX). App personal: [app-personalsalud/README.md](../app-personalsalud/README.md) (APS).
