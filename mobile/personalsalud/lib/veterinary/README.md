# Módulo veterinario

Este directorio reserva una vertical independiente dentro de Personal de Salud:

```text
veterinary/
  screens/
  models/
  services/
```

El módulo humano actual no se renombra ni se mezcla con pantallas veterinarias. La selección del módulo dependerá del ámbito comunicado por el backend; no se infiere desde el rol local ni desde identificadores de encounter. Los componentes neutrales pueden seguir viviendo en `packages/shared`.

No agregar todavía pantallas clínicas, rutas ni llamadas veterinarias: dependen de que se apruebe el alcance del MVP.
