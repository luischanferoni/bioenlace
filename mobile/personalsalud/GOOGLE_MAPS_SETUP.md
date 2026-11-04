# Google Maps — ubicación del efector (alta comercial)

El alta de un **espacio de atención privado** ubica el efector sobre un mapa y el
backend resuelve **país → provincia → departamento → localidad** a partir de las
coordenadas.

Se usan dos credenciales distintas de Google Maps Platform:

| Uso | API | Dónde vive la key |
|---|---|---|
| Render del mapa en la app | Maps SDK for Android / Maps SDK for iOS | build del móvil (no versionada) |
| Reverse geocoding en el servidor | Geocoding API | `web/common/config/params-local.php` |

> El `google-services.json` del proyecto es configuración de **Firebase** y el
> JSON de cuenta de servicio de Google Cloud sirve para **APIs server-side de
> Vertex/Gemini**: ninguno de los dos habilita mapas.

## 1. Google Cloud (una sola vez)

1. Entrar a [Google Cloud Console → Maps Platform](https://console.cloud.google.com/google/maps-apis)
   con el proyecto que ya usan para Gemini.
2. Habilitar las tres APIs:
   - **Geocoding API** (servidor)
   - **Maps SDK for Android**
   - **Maps SDK for iOS**
3. Confirmar que el proyecto tiene **facturación activa** (Maps Platform la exige).

## 2. Key del servidor (Geocoding)

1. *APIs & Services → Credentials → Create credentials → API key*.
2. Restringirla a **Geocoding API** y, si el servidor tiene IP fija, a esa IP.
3. Pegarla en `web/common/config/params-local.php`:

```php
'google_maps_geocoding_api_key' => 'AIza…',
```

Sin key, el backend no falla: resuelve la ubicación con la **localidad más
cercana** de `geo_localidades` (radio de 40 km) y devuelve `fuente: "base"`.

## 3. Key de Android (mapa)

1. Crear otra API key restringida a **Maps SDK for Android**, con restricción de
   aplicación por *package name* (`com.bioenlace.personalsalud`) + **SHA-1** del
   keystore (debug y release).
2. Agregarla en `mobile/personalsalud/android/local.properties` (archivo ignorado
   por git):

```properties
googleMapsApiKey=AIza…
```

Alternativa sin tocar archivos: exportar `GOOGLE_MAPS_API_KEY` en el entorno del
build. El `build.gradle.kts` toma primero `local.properties` y luego la variable
de entorno, y la inyecta en el manifest con el placeholder `${googleMapsApiKey}`
(`com.google.android.geo.API_KEY`).

Para ver el SHA-1 de debug:

```powershell
cd mobile/personalsalud/android
./gradlew signingReport
```

## 4. Key de iOS (mapa)

1. Crear una API key restringida a **Maps SDK for iOS** con el *bundle id* de la app.
2. Definir `GOOGLE_MAPS_API_KEY` como *User-Defined Setting* en el target Runner
   (o en un `.xcconfig` local no versionado).
3. `Info.plist` ya expone `GMSApiKey = $(GOOGLE_MAPS_API_KEY)` y el
   `AppDelegate` llama a `GMSServices.provideAPIKey(...)` cuando el valor existe.

## 4b. Key de Web (mapa en Chrome/navegador)

La app compila para web y el plugin federado `google_maps_flutter_web` ya viene
resuelto; falta la JS API y una key de navegador.

1. Habilitar **Maps JavaScript API** (cuarta API, distinta de Geocoding y de los
   SDK móviles).
2. Crear una API key restringida a **Maps JavaScript API** y, en *Application
   restrictions*, a **HTTP referrers**: `http://localhost:*`, `http://127.0.0.1:*`
   y el/los dominios de producción. **No reutilizar** la key de Android/iOS: esas
   están restringidas por package/bundle y el navegador las rechaza.
3. Pasar la key en el build (no se commitea):

```powershell
flutter run -d chrome --dart-define=GOOGLE_MAPS_WEB_API_KEY=AIza…
# producción
flutter build web --release --dart-define=GOOGLE_MAPS_WEB_API_KEY=AIza…
```

Cómo funciona: [google_maps_web_loader.dart](../mobile/personalsalud/lib/widgets/google_maps_web_loader.dart)
selecciona por *conditional import* la implementación web
(`google_maps_web_loader_web.dart`), que inyecta
`https://maps.googleapis.com/maps/api/js?...` una sola vez y espera `google.maps`;
en Android/iOS usa un stub. Si falta la key o la API no está habilitada, el widget
muestra un mensaje con **Reintentar** en lugar de romper el árbol de widgets.

> En web la key **queda visible en el navegador**: por eso la restricción por
> referrer es obligatoria. El *reverse geocoding* sigue siendo server-side con su
> propia key, que nunca se expone.

## 5. Comportamiento en la app

- Flujo **PRIVADO**: nombre + domicilio + mapa. El usuario toca el mapa o
  arrastra el marcador (o usa “Mi ubicación”); a los 400 ms se llama
  `POST /licencia/ubicacion` y se muestra `país · provincia · localidad` con la
  distancia a la localidad más cercana. El domicilio se autocompleta con la
  dirección devuelta por Google (editable).
- Al confirmar, el payload del alta envía `lat`, `lng` e `id_localidad`
  detectado; el backend persiste las coordenadas en `efectores.latitud/longitud`
  y, si faltara la localidad, la resuelve por cercanía.
- Flujo **PUBLICO** no cambia (el efector viene del catálogo SISA).

## 6. Verificación

```powershell
cd mobile/personalsalud
flutter analyze lib
flutter run            # con googleMapsApiKey cargada
```

En el servidor: `php -l` de los archivos tocados y `php yii migrate` para las
columnas nuevas (`efectores.latitud`, `efectores.longitud`).
