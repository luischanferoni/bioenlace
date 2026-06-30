# Build Android en Windows (proyecto en `D:`)

## Error «different roots» (Kotlin + Pub cache)

Si el repo está en `D:\` y el Pub cache en `C:\Users\...\AppData\Local\Pub\Cache`, Gradle/Kotlin puede fallar al compilar plugins (`record_android`, etc.) con:

```text
IllegalArgumentException: this and base files have different roots
```

**Ya aplicado en el repo:** `kotlin.incremental=false` en `android/gradle.properties` de cada app.

**Recomendado a largo plazo:** misma unidad para proyecto y cache:

```text
PUB_CACHE=D:\Pub\Cache
```

Reiniciar terminal/IDE, luego:

```bash
flutter clean
flutter pub get
```

## Error «failed to strip debug symbols»

Flutter **verifica** el `.aab` con `apkanalyzer` (parte de **Android SDK Command-line Tools**). Si no están instalados, Gradle puede terminar bien pero `flutter build appbundle` falla al final.

`flutter doctor` suele mostrar:

```text
X cmdline-tools component is missing.
```

### Opción A — Sin Android Studio (solo SDK + terminal)

Si ya tenés el SDK en `C:\Users\<usuario>\AppData\Local\Android\sdk` (Flutter lo detecta con `flutter doctor`):

1. Descargar **Command line tools only** para Windows:
   https://developer.android.com/studio#command-line-tools-only  
   (archivo `commandlinetools-win-*.zip`)

2. Descomprimir y dejar esta estructura (crear carpetas si no existen):

```text
%LOCALAPPDATA%\Android\sdk\cmdline-tools\latest\
    bin\sdkmanager.bat
    lib\...
```

   Es decir: el contenido del zip va dentro de `...\cmdline-tools\latest\`, no directamente en `cmdline-tools\`.

3. Instalar componentes y aceptar licencias:

```powershell
$env:ANDROID_HOME = "$env:LOCALAPPDATA\Android\sdk"
& "$env:ANDROID_HOME\cmdline-tools\latest\bin\sdkmanager.bat" --install "cmdline-tools;latest" "platform-tools"
& "$env:ANDROID_HOME\cmdline-tools\latest\bin\sdkmanager.bat" --licenses
flutter doctor -v
```

4. Rebuild:

```powershell
cd mobile\paciente
flutter build appbundle --release
```

### Opción B — Ignorar el mensaje de Flutter

Gradle **sí** genera el bundle aunque Flutter muestre error al final. Si el archivo existe y está firmado con tu keystore, **podés subirlo a Play**:

```text
mobile/paciente/build/app/outputs/bundle/release/app-release.aab
```

Build directo con Gradle (sin chequeo final de Flutter):

```powershell
cd mobile\paciente\android
.\gradlew --stop
.\gradlew :app:bundleRelease
```

### Opción C — Con Android Studio

Solo si lo usás: SDK Manager → **Android SDK Command-line Tools (latest)** → Apply, luego `flutter doctor --android-licenses`.

### Cierre de archivos en Windows

Si falla `lintVitalAnalyzeRelease` con «archivo en uso»:

1. El proyecto ya desactiva `lint.checkReleaseBuilds` en release (no bloquea el bundle).
2. Si persiste: `cd mobile/paciente/android` → `.\gradlew --stop`, cerrar otros procesos que usen `build/` (IDE, emulador) y borrar `build/`.
3. Reintentar `flutter build appbundle --release`.

## Release store

Ver [../RELEASE_SIGNING.md](../RELEASE_SIGNING.md) — **obligatorio** antes de subir a Play Console.
