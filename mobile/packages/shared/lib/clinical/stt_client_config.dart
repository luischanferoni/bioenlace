/// Configuración STT expuesta por GET /api/v1/audio/stt-config (sin secretos).
class SttClientConfig {
  const SttClientConfig({
    required this.deviceEnabled,
    required this.serverEnabled,
    required this.proveedorServidor,
    required this.serverConfigured,
    this.minConfidence = 0.75,
    this.profileMinConfidence = const {},
  });

  final bool deviceEnabled;
  final bool serverEnabled;
  final String proveedorServidor;
  final bool serverConfigured;
  final double minConfidence;

  /// Umbrales por perfil (`captura_clinica`, `motivos_consulta`, …).
  final Map<String, double> profileMinConfidence;

  static const defaults = SttClientConfig(
    deviceEnabled: true,
    serverEnabled: true,
    proveedorServidor: 'groq',
    serverConfigured: false,
    minConfidence: 0.75,
    profileMinConfidence: {
      'captura_clinica': 0.85,
      'motivos_consulta': 0.75,
    },
  );

  double minConfidenceForProfile(String profileId) {
    return profileMinConfidence[profileId] ?? minConfidence;
  }

  factory SttClientConfig.fromJson(Map<String, dynamic>? json) {
    if (json == null || json.isEmpty) {
      return defaults;
    }
    final profilesRaw = json['profiles'];
    final profiles = <String, double>{};
    if (profilesRaw is Map) {
      profilesRaw.forEach((key, value) {
        if (value is Map && value['min_confidence'] != null) {
          final v = double.tryParse(value['min_confidence'].toString());
          if (v != null) profiles[key.toString()] = v;
        } else if (value != null) {
          final v = double.tryParse(value.toString());
          if (v != null) profiles[key.toString()] = v;
        }
      });
    }
    final rawConf = json['min_confidence'];
    final base = rawConf == null
        ? 0.75
        : (double.tryParse(rawConf.toString()) ?? 0.75);
    return SttClientConfig(
      deviceEnabled: json['device_enabled'] != false,
      serverEnabled: json['server_enabled'] != false,
      proveedorServidor: (json['proveedor_servidor'] ?? 'groq').toString(),
      serverConfigured: json['server_configured'] == true,
      minConfidence: base,
      profileMinConfidence: {
        ...defaults.profileMinConfidence,
        ...profiles,
      },
    );
  }
}
