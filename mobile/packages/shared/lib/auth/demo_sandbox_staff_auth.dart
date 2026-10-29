import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../http/api_contract.dart';

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
      role == 'staff' ||
      role == 'enfermeria' ||
      role == 'administrativo' ||
      role == 'odontologia';

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
  static const _staffRoles = {'staff', 'enfermeria', 'administrativo', 'odontologia'};

  /// Perfiles staff del sandbox (sin paciente).
  static Future<List<DemoSandboxProfile>> listStaffProfiles({
    String appClient = 'bioenlace-personalsalud',
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/licencia/demo-perfiles');
    final response = await http
        .get(uri, headers: AppConfig.jsonHeaders(appClient: appClient))
        .timeout(const Duration(seconds: 15));
    final payload = unwrapApiContract(
      jsonDecode(response.body),
      response.statusCode,
    );
    final enabled = payload['enabled'];
    if (enabled == false) {
      return const [];
    }
    if (enabled != true) {
      throw const FormatException(
        'Respuesta inválida al consultar perfiles demo.',
      );
    }
    final items = payload['items'];
    if (items is! List) {
      throw const FormatException(
        'La respuesta de perfiles demo no contiene una lista.',
      );
    }
    final out = <DemoSandboxProfile>[];
    for (final it in items) {
      final p = DemoSandboxProfile.fromJson(it);
      if (p == null) {
        throw const FormatException(
          'La respuesta contiene un perfil demo inválido.',
        );
      }
      if (!_staffRoles.contains(p.role)) continue;
      out.add(p);
    }
    return out;
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
    return unwrapApiContract(data, response.statusCode);
  }

  static Future<Map<String, dynamic>> enterAsMedico({
    String appClient = 'bioenlace-personalsalud',
    String? email,
  }) {
    return enter(role: 'staff', appClient: appClient, email: email);
  }
}
