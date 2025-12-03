import 'package:flutter/material.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../services/chat_service.dart';
import '../theme/theme.dart';
import 'chat_screen.dart';
import 'signup_screen.dart';

class LoginScreen extends StatefulWidget {
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final LocalAuthentication auth = LocalAuthentication();
  bool _isAuthenticating = false;
  bool _biometricAvailable = false;
  String _biometricType = '';

  @override
  void initState() {
    super.initState();
    _checkBiometricAvailability();
  }

  Future<void> _checkBiometricAvailability() async {
    try {
      final isAvailable = await auth.canCheckBiometrics;
      final biometrics = await auth.getAvailableBiometrics();
      
      setState(() {
        _biometricAvailable = isAvailable && biometrics.isNotEmpty;
        if (biometrics.contains(BiometricType.fingerprint)) {
          _biometricType = 'Huella digital';
        } else if (biometrics.contains(BiometricType.face)) {
          _biometricType = 'Reconocimiento facial';
        } else if (biometrics.contains(BiometricType.iris)) {
          _biometricType = 'Reconocimiento de iris';
        }
      });
    } catch (e) {
      setState(() {
        _biometricAvailable = false;
      });
    }
  }

  Future<void> _loginWithBiometrics() async {
    if (!_biometricAvailable) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('La autenticación biométrica no está disponible en este dispositivo'),
          backgroundColor: AppTheme.warningColor,
        ),
      );
      return;
    }

    setState(() {
      _isAuthenticating = true;
    });

    try {
      final didAuthenticate = await auth.authenticate(
        localizedReason: 'Autenticación con $_biometricType para ingresar a BioEnlace',
        options: const AuthenticationOptions(
          biometricOnly: true,
          stickyAuth: true,
        ),
      );

      if (didAuthenticate) {
        // Obtener datos del usuario registrado
        final prefs = await SharedPreferences.getInstance();
        final userId = prefs.getString('user_id') ?? 'user_${DateTime.now().millisecondsSinceEpoch}';
        final userName = prefs.getString('provided_name') ?? 'Usuario';

        // Crear servicio de chat
        final chatService = ChatService(
          currentUserId: userId,
          currentUserName: userName,
        );

        // Mostrar mensaje de éxito
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('¡Bienvenido de vuelta, $userName!'),
            backgroundColor: AppTheme.successColor,
          ),
        );

        // Navegar a la pantalla de chat
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => ChatScreen(chatService: chatService)),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Autenticación cancelada'),
            backgroundColor: AppTheme.warningColor,
          ),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error en la autenticación: ${e.toString()}'),
          backgroundColor: AppTheme.dangerColor,
        ),
      );
    } finally {
      setState(() {
        _isAuthenticating = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        backgroundColor: AppTheme.backgroundColor,
        elevation: 0,
        iconTheme: IconThemeData(color: AppTheme.dark),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Icono de huella digital grande
              Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.fingerprint,
                  size: 60,
                  color: AppTheme.primaryColor,
                ),
              ),
              
              const SizedBox(height: 32),
              
              // Título principal
              Text(
                'Bienvenido a BioEnlace',
                style: AppTheme.h1Style.copyWith(
                  color: AppTheme.dark,
                  fontSize: 28,
                ),
                textAlign: TextAlign.center,
              ),
              
              const SizedBox(height: 8),
              
              Text(
                'Tu asistente de salud personal',
                style: AppTheme.subTitleStyle.copyWith(fontSize: 16),
                textAlign: TextAlign.center,
              ),
              
              const SizedBox(height: 48),
              
              // Información sobre biometría
              if (_biometricAvailable) ...[
                Container(
                  padding: EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppTheme.successColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppTheme.successColor.withOpacity(0.3),
                      width: 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.check_circle,
                        color: AppTheme.successColor,
                        size: 20,
                      ),
                      SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          '$_biometricType disponible',
                          style: AppTheme.h6Style.copyWith(
                            color: AppTheme.successColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
              ],
              
              // Botón principal de autenticación
              SizedBox(
                width: double.infinity,
                height: 56,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _biometricAvailable 
                        ? AppTheme.primaryColor 
                        : AppTheme.secondaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 2,
                  ),
                  onPressed: _biometricAvailable && !_isAuthenticating 
                      ? _loginWithBiometrics 
                      : null,
                  child: _isAuthenticating
                      ? Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            ),
                            SizedBox(width: 12),
                            Text(
                              'Autenticando...',
                              style: AppTheme.h5Style.copyWith(color: Colors.white),
                            ),
                          ],
                        )
                      : Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.fingerprint, size: 24),
                            SizedBox(width: 12),
                            Text(
                              _biometricAvailable 
                                  ? 'Ingresar con $_biometricType'
                                  : 'Biometría no disponible',
                              style: AppTheme.h5Style.copyWith(color: Colors.white),
                            ),
                          ],
                        ),
                ),
              ),
              
              const SizedBox(height: 32),
              
              // Botón de registro
              TextButton(
                style: TextButton.styleFrom(
                  foregroundColor: AppTheme.primaryColor,
                ),
                child: Text(
                  '¿No tienes cuenta? Regístrate aquí',
                  style: AppTheme.subTitleStyle.copyWith(
                    color: AppTheme.primaryColor,
                    fontSize: 14,
                    decoration: TextDecoration.underline,
                  ),
                ),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => SignupScreen()),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
