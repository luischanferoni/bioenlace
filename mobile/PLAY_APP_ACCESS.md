# Google Play — Detalles de acceso a la app

Las apps Bioenlace usan **biometría / Didit** en el flujo normal. Los revisores de Google **no** pueden usar ese camino sin una cuenta previa. Por eso hay un **acceso oculto** para revisión + cuentas dedicadas en el servidor.

## Dónde cargarlo en Play Console

1. **Panel de la app** → **Política y programas** → **Contenido de la app**
2. **Acceso a las apps** (App access)
3. Elegir: **Se restringe el acceso a algunas o todas las funciones de mi app**
4. Pegar las instrucciones (inglés recomendado) y credenciales de la sección [Texto para Play Console](#texto-para-play-console) según la app.

La **URL de política de privacidad** es independiente: `https://bioenlace.io/privacidad.html`

---

## Preparar el servidor (una vez por entorno)

En `web/frontend/config/params-local.php` del entorno que revisa Google (`app.bioenlace.io`):

```php
return [
    'play_review_login_habilitado' => true,
    'play_review_accounts' => [
        ['username' => 'play_review_paciente'],
        ['username' => 'medico_med_general_863'],
    ],
];
```

Solo los `username` listados pueden entrar por `POST /api/v1/auth/login-revision`. La contraseña se valida contra la BD (no va en params).

### Crear cuentas de revisión

**App paciente** (`com.bioenlace.paciente`):

```bash
cd web
php yii clinical-seed/play-review-paciente --playPassword='TU_CLAVE_SEGURA_PACIENTE'
```

Usuario por defecto: `play_review_paciente` · documento `39999001`

**App personal de salud** (`com.bioenlace.personalsalud`):

```bash
php yii clinical-seed/medico-med-general --efector=863 --password='TU_CLAVE_SEGURA_STAFF'
```

Usuario por defecto: `medico_med_general_863` (ver salida del comando).

Usá el mismo efector y datos demo que tengan turnos/camas si querés que el revisor vea contenido.

---

## Cómo entra el revisor en la app

1. Abrir la app (sin cuenta biométrica previa).
2. En la pantalla de login, **tocar el logo Bioenlace 5 veces** en menos de 3 segundos.
3. Se abre **«Acceso para revisión»**.
4. Ingresar usuario y contraseña de Play Console.
5. **Personal de salud:** completar el wizard de efector / servicio / área (elegir el efector demo, p. ej. MED GENERAL).

No hace falta Didit ni huella en este flujo.

---

## Texto para Play Console

### Bioenlace Paciente (`com.bioenlace.paciente`)

```
This app requires login. Use the hidden reviewer login:

1. On the welcome/login screen, tap the Bioenlace logo at the top 5 times quickly (within 3 seconds).
2. A sheet titled "Acceso para revisión" will open.
3. Sign in with:
   Username: play_review_paciente
   Password: [PASSWORD YOU SET ON SERVER]

The app connects to https://app.bioenlace.io/api/v1

No biometric hardware or Didit verification is required for this reviewer account.

Privacy policy: https://bioenlace.io/privacidad.html

If login fails, contact info@bioenlace.io — do not publish this email in the public store listing.
```

### Bioenlace Personal de Salud (`com.bioenlace.personalsalud`)

```
This app is for hospital staff only. Reviewer login: tap the Bioenlace logo 5 times within 3 seconds on the login screen. Username: medico_med_general_863. Password: [PASSWORD]. After sign-in, use the setup wizard: pick the demo health facility, select MED GENERAL, then choose work area OUTP (ambulatory) or EMER (emergency). API: https://app.bioenlace.io/api/v1. Dedicated Google Play review sandbox. Privacy: https://bioenlace.io/privacidad.html. Support: info@bioenlace.io (reviewers only).
```

Reemplazá `[PASSWORD]` por la clave real que configuraste con los comandos `clinical-seed` (no la subas al repo).

---

## Seguridad

| Medida | Detalle |
|--------|---------|
| Endpoint deshabilitado por defecto | `play_review_login_habilitado => false` en params |
| Allowlist | Solo usernames en `play_review_accounts` |
| UI oculta | 5 toques en logo; no aparece en capturas de marketing |
| Cuentas sandbox | Datos ficticios; rotar contraseña tras cada revisión si querés |
| Desactivar después | Podés poner `play_review_login_habilitado => false` cuando no haya revisión en curso |

---

## Checklist antes de enviar el AAB

- [ ] `play_review_login_habilitado = true` en producción (solo mientras revisan)
- [ ] Cuentas creadas con `clinical-seed` y probadas desde un dispositivo limpio
- [ ] Texto pegado en Play Console con contraseña correcta
- [ ] Política de privacidad: `https://bioenlace.io/privacidad.html`
- [ ] Versión/build incrementado en `pubspec.yaml` (`version: x.y.z+N`)

---

## Alternativa sin código (no recomendada)

Play permite indicar «contactar al desarrollador» para obtener acceso. En apps de salud Google suele **rechazar** o demorar la revisión. El flujo de arriba es el estándar que implementamos en el repo.
