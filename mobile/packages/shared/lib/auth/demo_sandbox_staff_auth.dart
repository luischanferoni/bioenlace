import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// Perfil CTA demo (catálogo GET /licencia/demo-perfiles).
class DemoSandboxProfile {
  final String role;
  final String label;
  final String mode;

  const DemoSandboxProfile({
    required this.role,
    required this.label,
    this.mode = 'ephemeral',
  });

  bool get isStaff =>
      role == 'staff' || role == 'enfermeria' || role == 'administrativo';

  static DemoSandboxProfile? fromJson(Object? raw) {
    if (raw is! Map) return null;
    final role = (raw['role']?.toString() ?? '').trim();
    if (role.isEmpty) return null;
    final label = (raw['label']?.toString() ?? '').trim();
    return DemoSandboxProfile(
      role: role,
      label: label.isNotEmpty ? label : role,
      mode: (raw['mode']?.toString() ?? 'ephemeral').trim(),
    );
  }
}

/// Acceso demo staff efímero (POST /licencia/demo-acceso-mobile).
///
/// Misma plantilla DEV + seed que el CTA institucional web, pero responde JWT
/// con contexto listo para Personal de Salud (sin password ni enter_url).
class DemoSandboxStaffAuth {
  static const _staffRoles = {'staff', 'enfermeria', 'administrativo'};

  /// Perfiles staff del sandbox (sin paciente).
  static Future<List<DemoSandboxProfile>> listStaffProfiles({
    String appClient = 'bioenlace-personalsalud',
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/licencia/demo-perfiles');
    try {
      final response = await http
          .get(
            uri,
            headers: AppConfig.jsonHeaders(appClient: appClient),
          )
          .timeout(const Duration(seconds: 15));
      final data = jsonDecode(response.body);
      if (response.statusCode < 200 ||
          response.statusCode >= 300 ||
          data is! Map ||
          data['success'] != true) {
        return const [];
      }
      final payload = data['data'];
      if (payload is! Map || payload['enabled'] != true) {
        return const [];
      }
      final items = payload['items'];
      if (items is! List) return const [];
      final out = <DemoSandboxProfile>[];
      for (final it in items) {
        final p = DemoSandboxProfile.fromJson(it);
        if (p == null || !_staffRoles.contains(p.role)) continue;
        out.add(p);
      }
      return out;
    } catch (_) {
      return const [];
    }
  }

  /// true si el entorno tiene demo habilitada y al menos un perfil staff.
  static Future<bool> probeEnabled({
    String appClient = 'bioenlace-personalsalud',
  }) async {
    final items = await listStaffProfiles(appClient: appClient);
    return items.isNotEmpty;
  }

  static Future<Map<String, dynamic>> enter({
    required String role,
    String appClient = 'bioenlace-personalsalud',
    String? email,
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/licencia/demo-acceso-mobile');
    final body = <String, dynamic>{
      // Honeypot (debe ir vacío).
      'website': '',
      'role': role.trim().isEmpty ? 'staff' : role.trim(),
    };
    final emailTrim = email?.trim() ?? '';
    if (emailTrim.isNotEmpty) {
      body['email'] = emailTrim;
    }

    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(appClient: appClient),
          body: jsonEncode(body),
        )
        .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

    final data = jsonDecode(response.body);
    if (response.statusCode >= 200 &&
        response.statusCode < 300 &&
        data is Map &&
        data['success'] == true) {
      return Map<String, dynamic>.from(data);
    }

    final message = data is Map
        ? (data['message'] ?? 'No se pudo abrir la demo')
        : 'No se pudo abrir la demo';
    throw Exception(message.toString());
  }

  static Future<Map<String, dynamic>> enterAsMedico({
    String appClient = 'bioenlace-personalsalud',
    String? email,
  }) {
    return enter(role: 'staff', appClient: appClient, email: email);
  }
}
