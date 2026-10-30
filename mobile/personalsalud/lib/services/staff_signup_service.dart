import 'dart:async';

import 'package:didit_sdk/sdk_flutter.dart';
import 'package:flutter/services.dart';
import 'package:shared/platform/didit_platform.dart';
import 'package:shared/shared.dart';

const _diditVerificationTimeout = Duration(minutes: 10);

/// Alta de cuenta de personal de salud: identidad (Didit) → cuenta → consultorio.
class StaffSignupService {
  /// Corre el KYC de Didit y devuelve el `verification_id` aprobado.
  Future<String> verifyIdentity() async {
    if (!isDiditSupported) {
      throw Exception(diditUnsupportedPlatformMessage);
    }
    final workflowId = await DiditConfigResolver.resolvePacienteKycWorkflowId();
    if (workflowId == null) {
      throw Exception('El alta no está configurada (workflow Didit).');
    }

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

  /// Crea la cuenta de identidad (Persona + User passwordless).
  Future<Map<String, dynamic>> createAccount({
    required String verificationId,
    required String email,
  }) {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/crear-cuenta',
    ).postData('/licencia/crear-cuenta', body: {
      'verification_id': verificationId,
      'email': email.trim(),
    });
  }

  /// Crea el consultorio privado + licencia (pago simulado) sobre la identidad.
  Future<Map<String, dynamic>> createConsultorio({
    required String verificationId,
    required String email,
    required String nombre,
    required String domicilio,
  }) {
    return ApiClient(
      appClient: 'bioenlace-personalsalud',
      traceLabel: 'licencia/registrar-efector',
    ).postData('/licencia/registrar-efector', body: {
      'perfil': 'CONSULTORIO',
      'sector': 'PRIVADO',
      'verification_id': verificationId,
      'email': email.trim(),
      'efector': {
        'nombre': nombre.trim(),
        'domicilio': domicilio.trim(),
      },
      'plan': {
        'classes': {
          'AMB': {
            'attentions_per_month': 200,
            'max_pes': 1,
          },
        },
      },
      'payment': {
        'card_number': '4242424242424242',
        'card_holder': nombre.trim(),
      },
    });
  }
}
