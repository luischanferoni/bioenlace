import 'dart:async';

import 'package:didit_sdk/sdk_flutter.dart';
import 'package:flutter/services.dart';
import 'package:shared/platform/didit_platform.dart';
import 'package:shared/shared.dart';

const _diditVerificationTimeout = Duration(minutes: 10);

/// Alta de cuenta profesional: identidad (Didit) → cuenta passwordless.
class StaffSignupService {
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
    ).postData('/auth/login-biometrico', body: {
      'biometric_verification_id': verificationId,
    });
  }

  /// Crea la cuenta de identidad (Persona + User passwordless).
  Future<Map<String, dynamic>> createAccount({
    required String verificationId,
    String? email,
  }) {
    final body = <String, dynamic>{'verification_id': verificationId};
    final normalizedEmail = email?.trim();
    if (normalizedEmail != null && normalizedEmail.isNotEmpty) {
      body['email'] = normalizedEmail;
    }

    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/crear-cuenta',
    ).postData('/licencia/crear-cuenta', body: body);
  }
}
