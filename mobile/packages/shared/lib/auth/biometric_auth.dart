import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';

class BiometricAuth {
  final LocalAuthentication _auth = LocalAuthentication();
  
  /// Verifica si la autenticación biométrica está disponible
  Future<bool> isAvailable() async {
    try {
      final isAvailable = await _auth.canCheckBiometrics;
      final biometrics = await _auth.getAvailableBiometrics();
      return isAvailable && biometrics.isNotEmpty;
    } catch (e) {
      return false;
    }
  }

  /// Obtiene el tipo de biometría disponible
  Future<String> getBiometricType() async {
    try {
      final biometrics = await _auth.getAvailableBiometrics();
      if (biometrics.contains(BiometricType.fingerprint)) {
        return 'Huella digital';
      } else if (biometrics.contains(BiometricType.face)) {
        return 'Reconocimiento facial';
      } else if (biometrics.contains(BiometricType.iris)) {
        return 'Reconocimiento de iris';
      }
      return '';
    } catch (e) {
      return '';
    }
  }

  /// Autentica al usuario usando biometría
  /// Retorna un mapa con 'success' (bool) y 'error' (String?) para mejor manejo de errores
  Future<Map<String, dynamic>> authenticate(String localizedReason) async {
    try {
      final didAuthenticate = await _auth.authenticate(
        localizedReason: localizedReason,
        options: const AuthenticationOptions(
          biometricOnly: true,
          stickyAuth: true,
        ),
      );
      return {
        'success': didAuthenticate,
        'error': didAuthenticate ? null : 'Autenticación cancelada por el usuario',
      };
    } on PlatformException catch (e) {
      String? errorMessage;
      bool isUserCancel = false;
      
      switch (e.code) {
        case 'NotAvailable':
          errorMessage = 'La autenticación biométrica no está disponible';
          break;
        case 'NotEnrolled':
          errorMessage = 'No hay huellas digitales registradas en el dispositivo';
          break;
        case 'LockedOut':
          errorMessage = 'La autenticación biométrica está bloqueada temporalmente';
          break;
        case 'PermanentlyLockedOut':
          errorMessage = 'La autenticación biométrica está bloqueada permanentemente';
          break;
        case 'UserCancel':
          errorMessage = null; // El usuario canceló, no es un error
          isUserCancel = true;
          break;
        default:
          errorMessage = 'Error en la autenticación: ${e.message ?? "Error desconocido"}';
      }
      return {
        'success': false,
        'error': errorMessage,
        'isUserCancel': isUserCancel,
      };
    } catch (e) {
      return {
        'success': false,
        'error': 'Error inesperado: ${e.toString()}',
      };
    }
  }
}

