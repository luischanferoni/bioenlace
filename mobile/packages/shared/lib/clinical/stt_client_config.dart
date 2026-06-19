/// Configuración STT expuesta por GET /api/v1/audio/stt-config (sin secretos).
class SttClientConfig {
  const SttClientConfig({
    required this.deviceEnabled,
    required this.serverEnabled,
    required this.proveedorServidor,
    required this.serverConfigured,
  });

  final bool deviceEnabled;
  final bool serverEnabled;
  final String proveedorServidor;
  final bool serverConfigured;

  static const defaults = SttClientConfig(
    deviceEnabled: true,
    serverEnabled: true,
    proveedorServidor: 'groq',
    serverConfigured: false,
  );

  factory SttClientConfig.fromJson(Map<String, dynamic>? json) {
    if (json == null || json.isEmpty) {
      return defaults;
    }
    return SttClientConfig(
      deviceEnabled: json['device_enabled'] != false,
      serverEnabled: json['server_enabled'] != false,
      proveedorServidor: (json['proveedor_servidor'] ?? 'groq').toString(),
      serverConfigured: json['server_configured'] == true,
    );
  }
}
