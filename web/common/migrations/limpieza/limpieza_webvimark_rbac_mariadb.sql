/* 
Limpieza Webvimark / RBAC (MariaDB)
===================================

Objetivo (por partes, como se pidió):
  1) Eliminar todo lo asociado con usuarios inexistentes.
  2) Eliminar rutas no asociadas a ningún permiso (permisos -> rutas).
  3) Eliminar permisos no asociados a ningún usuario (y desconectados del grafo RBAC).

IMPORTANTE
  - Este script asume esquema RBAC estándar:
      user(id)
      auth_item(name, type, ...)
      auth_item_child(parent, child)
      auth_assignment(user_id, item_name)
  - En este proyecto se usa auth_item.type:
      1 = role
      2 = permission
      3 = route
  - Ejecutar idealmente en una ventana de mantenimiento, con backup.

Modo de uso
  - Cada sección tiene un "PREVIEW" (SELECT) para ver impacto.
  - Luego un bloque de DELETE dentro de START TRANSACTION/COMMIT.
  - Si algo no te convence, hacé ROLLBACK en vez de COMMIT.
*/

/* =========================================================
   0) Conteos iniciales (observabilidad)
   ========================================================= */
SELECT
  (SELECT COUNT(*) FROM `user`) AS users_total,
  (SELECT COUNT(*) FROM auth_item WHERE type = 1) AS roles_total,
  (SELECT COUNT(*) FROM auth_item WHERE type = 2) AS permissions_total,
  (SELECT COUNT(*) FROM auth_item WHERE type = 3) AS routes_total,
  (SELECT COUNT(*) FROM auth_assignment) AS assignments_total,
  (SELECT COUNT(*) FROM auth_item_child) AS edges_total;


/* =========================================================
   1) Eliminar todo lo asociado con usuarios inexistentes
   ========================================================= */

/* 1A) PREVIEW: auth_assignment con user_id inexistente */
SELECT aa.*
FROM auth_assignment aa
LEFT JOIN `user` u ON u.id = aa.user_id
WHERE u.id IS NULL;

/* 1B) DELETE: auth_assignment con user_id inexistente */
START TRANSACTION;

DELETE aa
FROM auth_assignment aa
LEFT JOIN `user` u ON u.id = aa.user_id
WHERE u.id IS NULL;

COMMIT;

/* 1C) PREVIEW: auth_assignment apuntando a auth_item inexistente */
SELECT aa.*
FROM auth_assignment aa
LEFT JOIN auth_item ai ON ai.name = aa.item_name
WHERE ai.name IS NULL;

/* 1D) DELETE: auth_assignment apuntando a auth_item inexistente */
START TRANSACTION;

DELETE aa
FROM auth_assignment aa
LEFT JOIN auth_item ai ON ai.name = aa.item_name
WHERE ai.name IS NULL;

COMMIT;


/* =========================================================
   2) Eliminar rutas no asociadas a ningún permiso
      Interpretación: rutas (type=3) que NO son child de ningún
      permiso (type=2) vía auth_item_child(parent=perm, child=route)
   ========================================================= */

/* 2A) PREVIEW: rutas huérfanas (type=3) sin padre permission(type=2) */
SELECT r.name AS route_name
FROM auth_item r
WHERE r.type = 3
  AND NOT EXISTS (
    SELECT 1
    FROM auth_item_child aic
    JOIN auth_item p ON p.name = aic.parent AND p.type = 2
    WHERE aic.child = r.name
  )
ORDER BY r.name;

/* 2B) DELETE: borrar relaciones y luego borrar rutas huérfanas */
START TRANSACTION;

/* 2B.1) Borrar edges donde child es una ruta huérfana */
DELETE aic
FROM auth_item_child aic
JOIN auth_item r ON r.name = aic.child AND r.type = 3
WHERE NOT EXISTS (
  SELECT 1
  FROM auth_item_child aic2
  JOIN auth_item p ON p.name = aic2.parent AND p.type = 2
  WHERE aic2.child = r.name
);

/* 2B.2) Borrar el auth_item de la ruta huérfana */
DELETE r
FROM auth_item r
WHERE r.type = 3
  AND NOT EXISTS (
    SELECT 1
    FROM auth_item_child aic
    JOIN auth_item p ON p.name = aic.parent AND p.type = 2
    WHERE aic.child = r.name
  );

COMMIT;


/* =========================================================
   3) Eliminar permisos no asociados a ningún usuario
      Recomendado (seguro): borrar SOLO permisos (type=2) que:
        - no están asignados a ningún usuario (auth_assignment)
        - no tienen padres (no son child de nada)
        - no tienen hijos (no son parent de nada)

      Nota: si preferís una limpieza más agresiva (por ejemplo:
      permisos que no llegan a usuarios vía roles), eso requiere un
      análisis de transitividad del grafo (más pesado).
   ========================================================= */

/* 3A) PREVIEW: permisos totalmente desconectados del grafo RBAC */
SELECT p.name AS permission_name
FROM auth_item p
WHERE p.type = 2
  AND NOT EXISTS (SELECT 1 FROM auth_assignment aa WHERE aa.item_name = p.name)
  AND NOT EXISTS (SELECT 1 FROM auth_item_child aic WHERE aic.child = p.name)
  AND NOT EXISTS (SELECT 1 FROM auth_item_child aic WHERE aic.parent = p.name)
ORDER BY p.name;

/* 3B) DELETE: permisos totalmente desconectados */
START TRANSACTION;

DELETE p
FROM auth_item p
WHERE p.type = 2
  AND NOT EXISTS (SELECT 1 FROM auth_assignment aa WHERE aa.item_name = p.name)
  AND NOT EXISTS (SELECT 1 FROM auth_item_child aic WHERE aic.child = p.name)
  AND NOT EXISTS (SELECT 1 FROM auth_item_child aic WHERE aic.parent = p.name);

COMMIT;


/* =========================================================
   4) (Opcional recomendado) Higiene: auth_item_child roto
      - parent inexistente o child inexistente
   ========================================================= */

/* 4A) PREVIEW: edges rotos */
SELECT aic.*
FROM auth_item_child aic
LEFT JOIN auth_item p ON p.name = aic.parent
LEFT JOIN auth_item c ON c.name = aic.child
WHERE p.name IS NULL OR c.name IS NULL;

/* 4B) DELETE: edges rotos */
START TRANSACTION;

DELETE aic
FROM auth_item_child aic
LEFT JOIN auth_item p ON p.name = aic.parent
LEFT JOIN auth_item c ON c.name = aic.child
WHERE p.name IS NULL OR c.name IS NULL;

COMMIT;


/* =========================================================
   5) Conteos finales
   ========================================================= */
SELECT
  (SELECT COUNT(*) FROM `user`) AS users_total,
  (SELECT COUNT(*) FROM auth_item WHERE type = 1) AS roles_total,
  (SELECT COUNT(*) FROM auth_item WHERE type = 2) AS permissions_total,
  (SELECT COUNT(*) FROM auth_item WHERE type = 3) AS routes_total,
  (SELECT COUNT(*) FROM auth_assignment) AS assignments_total,
  (SELECT COUNT(*) FROM auth_item_child) AS edges_total;

