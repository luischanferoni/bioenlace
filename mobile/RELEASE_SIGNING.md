# Firma release (Google Play)

Play Console **rechaza** bundles firmados con la clave **debug**. Hay que usar un keystore de **upload** propio.

## 1. Crear keystore (una vez por app o compartido)

Desde PowerShell, en la carpeta donde guardarás el `.jks` (ej. `mobile/paciente/android/`):

```powershell
keytool -genkeypair -v `
  -keystore upload-keystore.jks `
  -alias upload `
  -keyalg RSA -keysize 2048 `
  -validity 10000 `
  -storetype PKCS12
```

- Guardá **contraseñas y alias** en un gestor seguro (1Password, etc.).
- **No subas** el `.jks` ni `key.properties` al repo (ya están en `.gitignore`).
- Podés usar el **mismo** `upload-keystore.jks` para paciente y personalsalud (mismo `storeFile` en cada `key.properties`) o uno por app.

## 2. Configurar `key.properties`

En cada app:

```text
mobile/paciente/android/key.properties
mobile/personalsalud/android/key.properties
```

Copiá desde `key.properties.example` y completá:

```properties
storePassword=...
keyPassword=...
keyAlias=upload
storeFile=upload-keystore.jks
```

`storeFile` es relativo a `android/app/` (donde está `build.gradle.kts`). Si el keystore está en `android/`:

```properties
storeFile=../upload-keystore.jks
```

## 3. Generar App Bundle firmado en release

```bash
cd mobile/paciente
flutter clean
flutter pub get
flutter build appbundle --release
```

Salida: `build/app/outputs/bundle/release/app-release.aab`

## 4. Verificar firma (opcional)

```powershell
jarsigner -verify -verbose -certs build\app\outputs\bundle\release\app-release.aab
```

No debe decir `CN=Android Debug`. Debe mostrar tu certificado de upload.

## 5. Play App Signing

En Play Console, Google puede guardar la **app signing key** y vos subís con la **upload key** (este keystore). Conservá el keystore: sin él no podés publicar actualizaciones.

## Desarrollo local

Sin `key.properties`, el build **release** sigue usando debug (solo para probar en dispositivo). **No subas ese AAB a Play.**
