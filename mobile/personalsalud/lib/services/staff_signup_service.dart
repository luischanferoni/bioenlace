import 'dart:async';

import 'package:didit_sdk/sdk_flutter.dart';
import 'package:flutter/services.dart';
import 'package:shared/platform/didit_platform.dart';
import 'package:shared/shared.dart';

const _diditVerificationTimeout = Duration(minutes: 10);

/// Alta de cuenta profesional: identidad (Didit) → cuenta passwordless.
class StaffSignupService {
  final PendingSignupVerificationStore _pendingVerificationStore =
      PendingSignupVerificationStore.instance;

  Future<String?> readPendingVerificationId() =>
      _pendingVerificationStore.read();

  Future<void> storePendingVerificationId(String verificationId) =>
      _pendingVerificationStore.write(verificationId);

  Future<void> clearPendingVerificationId() =>
      _pendingVerificationStore.delete();

  /// Corre una verificación de Didit y devuelve el `session_id` aprobado.
  Future<String> runDiditVerification({required bool biometric}) async {
    if (!isDiditSupported) {
      throw Exception(diditUnsupportedPlatformMessage);
    }
    final workflowId = biometric
        ? await DiditConfigResolver.resolvePacienteBiometricWorkflowId()
        : await DiditConfigResolver.resolvePacienteKycWorkflowId();
    if (workflowId == null) {
      // Fallback: sin workflow biométrico configurado, usar el KYC.
      final kyc = await DiditConfigResolver.resolvePacienteKycWorkflowId();
      if (kyc == null) {
        throw Exception('El alta no está configurada. Reintentá más tarde.');
      }
      return _startVerification(kyc);
    }
    return _startVerification(workflowId);
  }

  Future<String> _startVerification(String workflowId) async {
    try {
      final result = await DiditSdk.startVerificationWithWorkflow(
        workflowId,
        config: const DiditConfig(languageCode: 'es', loggingEnabled: true),
      ).timeout(_diditVerificationTimeout);

      switch (result) {
        case VerificationCompleted(:final session):
          if (session.status == VerificationStatus.approved) {
            return session.sessionId;
          }
          throw Exception('Verificación en estado: ${session.status.name}');
        case VerificationCancelled():
          throw Exception('Verificación cancelada.');
        case VerificationFailed(:final error):
          throw Exception(
            'No se pudo completar la verificación: ${error.message}',
          );
      }
    } on MissingPluginException {
      throw Exception(diditMissingPluginMessage);
    } on TimeoutException {
      throw Exception('La verificación tardó demasiado. Intentá de nuevo.');
    }
  }

  /// Corre el KYC de Didit y devuelve el `verification_id` aprobado.
  Future<String> verifyIdentity() => runDiditVerification(biometric: false);

  /// Re-verifica identidad con Didit y hace login biométrico (dispositivo nuevo).
  Future<Map<String, dynamic>> loginExisting() async {
    final verificationId = await runDiditVerification(biometric: true);
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'auth/login-biometrico',
    ).postData(
      '/auth/login-biometrico',
      body: {'biometric_verification_id': verificationId},
    );
  }

  /// Crea la cuenta de identidad (Persona + User passwordless).
  Future<Map<String, dynamic>> createAccount({
    required String verificationId,
    String? email,
    int? idEfector,
  }) {
    final body = <String, dynamic>{'verification_id': verificationId};
    final normalizedEmail = email?.trim();
    if (normalizedEmail != null && normalizedEmail.isNotEmpty) {
      body['email'] = normalizedEmail;
    }
    if (idEfector != null && idEfector > 0) {
      body['id_efector'] = idEfector;
    }

    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/crear-cuenta',
    ).postData('/licencia/crear-cuenta', body: body);
  }

  Future<Map<String, dynamic>> fetchSignupPlans() {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/planes',
    ).getData('/licencia/planes');
  }

  Future<Map<String, dynamic>> quoteSignupPlan({
    required Map<String, dynamic> plan,
  }) {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/cotizar',
    ).postData('/licencia/cotizar', body: {'plan': plan});
  }

  Future<List<Map<String, dynamic>>> searchLocalities(String query) async {
    final data = await ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'catalogos/buscar-localidad',
    ).getData('/catalogos/buscar-localidad', query: {'q': query});
    final items = data['items'];
    if (items is! List) {
      throw const FormatException('Respuesta inválida al buscar localidades.');
    }
    return items.whereType<Map>().map((item) {
      return item.map((key, value) => MapEntry(key.toString(), value));
    }).toList();
  }

  Future<List<Map<String, dynamic>>> fetchMinistries() async {
    final data = await ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/catalogo-ministerios',
    ).getData('/licencia/catalogo-ministerios');
    final items = data['items'];
    if (items is! List) {
      throw const FormatException('Respuesta inválida al cargar ministerios.');
    }
    return items.whereType<Map>().map((item) {
      return item.map((key, value) => MapEntry(key.toString(), value));
    }).toList();
  }

  Future<List<Map<String, dynamic>>> searchPublicEffectors(String query) async {
    final data = await ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/catalogo-efectores-publicos',
    ).getData('/licencia/catalogo-efectores-publicos', query: {'q': query});
    final items = data['items'];
    if (items is! List) {
      throw const FormatException(
        'Respuesta inválida al buscar efectores públicos.',
      );
    }
    return items.whereType<Map>().map((item) {
      return item.map((key, value) => MapEntry(key.toString(), value));
    }).toList();
  }

  Future<List<Map<String, dynamic>>> fetchProvinces() async {
    final data = await ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/catalogo-provincias',
    ).getData('/licencia/catalogo-provincias');
    final items = data['items'];
    if (items is! List) {
      throw const FormatException('Respuesta inválida al cargar provincias.');
    }
    return items.whereType<Map>().map((item) {
      return item.map((key, value) => MapEntry(key.toString(), value));
    }).toList();
  }

  Future<List<Map<String, dynamic>>> searchCenters({
    String query = '',
    String? sector,
    int? idProvincia,
  }) async {
    final data =
        await ApiClient(
          appClient: 'bioenlace-personalsalud',
          traceLabel: 'licencia/catalogo-centros',
        ).getData(
          '/licencia/catalogo-centros',
          query: {
            'q': query.trim(),
            if (sector != null && sector.isNotEmpty) 'sector': sector,
            if (idProvincia != null && idProvincia > 0)
              'id_provincia': '$idProvincia',
          },
        );
    final items = data['items'];
    if (items is! List) {
      throw const FormatException('Respuesta inválida al cargar centros.');
    }
    return items.whereType<Map>().map((item) {
      return item.map((key, value) => MapEntry(key.toString(), value));
    }).toList();
  }

  Future<Map<String, dynamic>> startCommercialCheckout({
    required String verificationId,
    required Map<String, dynamic> body,
  }) {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/registrar-efector-didit',
    ).postData(
      '/licencia/registrar-efector-didit',
      body: {...body, 'verification_id': verificationId},
    );
  }

  Future<Map<String, dynamic>> checkoutStatus({
    required String verificationId,
    required String checkoutKey,
  }) {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/estado-checkout-didit',
    ).postData(
      '/licencia/estado-checkout-didit',
      body: {'verification_id': verificationId, 'checkout_key': checkoutKey},
    );
  }

  Future<Map<String, dynamic>> saveCommercialDraft({
    required String verificationId,
    required Map<String, dynamic> draft,
  }) {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/guardar-borrador',
    ).postData(
      '/licencia/guardar-borrador',
      body: {'verification_id': verificationId, 'borrador': draft},
    );
  }

  Future<Map<String, dynamic>> cancelCommercialCheckout({
    required String verificationId,
    required String checkoutKey,
  }) {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/cancelar-suscripcion-didit',
    ).postData(
      '/licencia/cancelar-suscripcion-didit',
      body: {'verification_id': verificationId, 'checkout_key': checkoutKey},
    );
  }
}
