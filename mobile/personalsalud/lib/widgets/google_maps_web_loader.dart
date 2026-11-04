/// Selecciona la implementación del cargador de Google Maps según la plataforma.
///
/// En web (`dart.library.js_interop` disponible) carga la JS API; en Android/iOS
/// usa un stub sin dependencias de `dart:js_interop`.
library;

export 'google_maps_web_loader_stub.dart'
    if (dart.library.js_interop) 'google_maps_web_loader_web.dart';
