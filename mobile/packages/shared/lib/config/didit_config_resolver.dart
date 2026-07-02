import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_config.dart';

/// Resuelve IDs de workflow Didit: `--dart-define` en build o API pública del backend.
class DiditConfigResolver {
  DiditConfigResolver._();

  static _MobileDiditConfig? _cache;

  static Future<String?> resolvePacienteKycWorkflowId() async {
    final fromDefine = AppConfig.diditPacienteKycWorkflowId;
    if (!AppConfig.isDiditWorkflowPlaceholder(fromDefine)) {
      return fromDefine;
    }
    final remote = await _fetchMobileConfig();
    final id = remote?.pacienteKyc;
    if (id == null || AppConfig.isDiditWorkflowPlaceholder(id)) return null;
    return id;
  }

  static Future<String?> resolvePacienteBiometricWorkflowId() async {
    final fromDefine = AppConfig.diditPacienteBiometricWorkflowId;
    if (!AppConfig.isDiditWorkflowPlaceholder(fromDefine)) {
      return fromDefine;
    }
    final remote = await _fetchMobileConfig();
    final bio = remote?.pacienteBiometric;
    if (bio != null && !AppConfig.isDiditWorkflowPlaceholder(bio)) {
      return bio;
    }
    return null;
  }

  /// Limpia caché de config Didit (p. ej. tras logout).
  static void clearCache() {
    _cache = null;
  }

  static Future<_MobileDiditConfig?> _fetchMobileConfig() async {
    if (_cache != null) return _cache;
    return _loadMobileConfig();
  }

  static Future<_MobileDiditConfig?> _loadMobileConfig() async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/registro/config-movil');
      final response = await http
          .get(
            uri,
            headers: AppConfig.jsonHeaders(appClient: 'bioenlace-flutter'),
          )
          .timeout(const Duration(seconds: 20));

      if (response.statusCode < 200 || response.statusCode >= 300) {
        return null;
      }

      final body = jsonDecode(response.body);
      if (body is! Map<String, dynamic> || body['success'] != true) {
        return null;
      }

      final data = body['data'];
      if (data is! Map<String, dynamic>) return null;

      _cache = _MobileDiditConfig(
        pacienteKyc: _trimOrNull(data['didit_paciente_kyc_workflow_id']),
        pacienteBiometric: _trimOrNull(
          data['didit_paciente_biometric_workflow_id'],
        ),
      );
      return _cache;
    } catch (_) {
      return null;
    }
  }

  static String? _trimOrNull(Object? value) {
    if (value == null) return null;
    final s = value.toString().trim();
    return s.isEmpty ? null : s;
  }
}

class _MobileDiditConfig {
  const _MobileDiditConfig({
    required this.pacienteKyc,
    required this.pacienteBiometric,
  });

  final String? pacienteKyc;
  final String? pacienteBiometric;
}
