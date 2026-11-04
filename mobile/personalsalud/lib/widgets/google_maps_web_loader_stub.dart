/// Implementación para Android/iOS: el SDK nativo no necesita cargar JS.
class GoogleMapsWebLoader {
  static bool get isConfigured => true;

  static Future<bool> ensureLoaded() async => true;
}
