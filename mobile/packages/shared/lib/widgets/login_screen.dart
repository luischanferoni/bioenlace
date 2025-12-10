import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../auth/biometric_auth.dart';
import '../theme/theme.dart';

/// Pantalla de login compartida que acepta callbacks para navegación personalizada
class LoginScreen extends StatefulWidget {
  /// Título de la app (ej: "Bienvenido a BioEnlace")
  final String appTitle;
  
  /// Subtítulo de la app (ej: "Tu asistente de salud personal")
  final String appSubtitle;
  
  /// Callback cuando el login es exitoso
  /// Recibe userId, userName y BuildContext del LoginScreen
  final Function(String userId, String userName, BuildContext context) onLoginSuccess;
  
  /// Callback para navegar a la pantalla de registro
  /// Recibe BuildContext del LoginScreen
  final Function(BuildContext context)? onNavigateToSignup;
  
  /// Callback para navegar al inicio de la app sin registrarse (modo visitante)
  /// Recibe BuildContext del LoginScreen
  final Function(BuildContext context)? onNavigateToHome;
  
  // Textos personalizables
  /// Mensaje cuando la biometría no está disponible
  final String? biometricNotAvailableMessage;
  
  /// Mensaje de bienvenida después del login (se puede usar {userName} como placeholder)
  final String? welcomeMessage;
  
  /// Texto del botón mientras se autentica
  final String? authenticatingText;
  
  /// Texto del botón cuando la biometría no está disponible
  final String? biometricUnavailableButtonText;
  
  /// Texto del botón de registro
  final String? signupButtonText;
  
  /// Texto del botón para ir al inicio
  final String? goToHomeButtonText;
  
  /// Texto cuando la biometría está disponible
  final String? biometricAvailableText;

  const LoginScreen({
    Key? key,
    this.appTitle = 'Bienvenido a BioEnlace',
    this.appSubtitle = 'Tu asistente de salud personal',
    required this.onLoginSuccess,
    this.onNavigateToSignup,
    this.onNavigateToHome,
    this.biometricNotAvailableMessage,
    this.welcomeMessage,
    this.authenticatingText,
    this.biometricUnavailableButtonText,
    this.signupButtonText,
    this.goToHomeButtonText,
    this.biometricAvailableText,
  }) : super(key: key);

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final BiometricAuth _biometricAuth = BiometricAuth();
  bool _isAuthenticating = false;
  bool _biometricAvailable = false;
  String _biometricType = '';

  @override
  void initState() {
    super.initState();
    _checkBiometricAvailability();
  }

  Future<void> _checkBiometricAvailability() async {
    final isAvailable = await _biometricAuth.isAvailable();
    final biometricType = await _biometricAuth.getBiometricType();
    
    setState(() {
      _biometricAvailable = isAvailable;
      _biometricType = biometricType;
    });
  }

  Future<void> _loginWithBiometrics() async {
    if (!_biometricAvailable) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(widget.biometricNotAvailableMessage ?? 
              'La autenticación biométrica no está disponible en este dispositivo'),
          backgroundColor: AppTheme.warningColor,
        ),
      );
      return;
    }

    setState(() {
      _isAuthenticating = true;
    });

    try {
      final result = await _biometricAuth.authenticate(
        'Autenticación con $_biometricType para ingresar a ${widget.appTitle}',
      );

      if (result['success'] == true) {
        print('[DEBUG] _loginWithBiometrics - Autenticación exitosa');
        // Obtener datos del usuario registrado
        final prefs = await SharedPreferences.getInstance();
        // Usar valores simulados si existen, sino usar valores por defecto
        final userId = prefs.getString('user_id') ?? '5748';
        final userName = prefs.getString('user_name') ?? prefs.getString('provided_name') ?? 'Usuario Médico';
        
        print('[DEBUG] _loginWithBiometrics - userId: $userId, userName: $userName');
        
        // Asegurar que rrhh_id esté guardado para el usuario simulado
        if (!prefs.containsKey('rrhh_id')) {
          await prefs.setString('rrhh_id', '7830');
          print('[DEBUG] _loginWithBiometrics - rrhh_id guardado: 7830');
        }
        
        // Simular token de autenticación (en producción esto vendría del servidor)
        // Para el usuario simulado 5748, usar un token simulado
        final authTokenExists = prefs.containsKey('auth_token');
        if (!authTokenExists) {
          final newToken = 'simulated_token_${userId}_${DateTime.now().millisecondsSinceEpoch}';
          await prefs.setString('auth_token', newToken);
          print('[DEBUG] _loginWithBiometrics - auth_token creado: ${newToken.substring(0, newToken.length > 30 ? 30 : newToken.length)}...');
        } else {
          final existingToken = prefs.getString('auth_token');
          print('[DEBUG] _loginWithBiometrics - auth_token ya existe: ${existingToken != null ? existingToken.substring(0, existingToken.length > 30 ? 30 : existingToken.length) : "null"}...');
        }
        
        // Asegurar que el auth_token se haya guardado completamente
        await prefs.reload();
        print('[DEBUG] _loginWithBiometrics - SharedPreferences recargado');
        print('[DEBUG] _loginWithBiometrics - config_completed: ${prefs.getBool('config_completed') ?? false}');

        // Mostrar mensaje de éxito
        final welcomeMsg = widget.welcomeMessage ?? '¡Bienvenido de vuelta, {userName}!';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(welcomeMsg.replaceAll('{userName}', userName)),
            backgroundColor: AppTheme.successColor,
          ),
        );

        // Llamar al callback de éxito después de un pequeño delay para asegurar que el contexto esté listo
        print('[DEBUG] _loginWithBiometrics - Esperando delay antes de llamar onLoginSuccess...');
        await Future.delayed(const Duration(milliseconds: 200));
        print('[DEBUG] _loginWithBiometrics - Llamando onLoginSuccess...');
        if (mounted) {
          widget.onLoginSuccess(userId, userName, context);
          print('[DEBUG] _loginWithBiometrics - onLoginSuccess llamado');
        }
      } else {
        // Solo mostrar mensaje si no fue cancelación del usuario
        final isUserCancel = result['isUserCancel'] == true;
        final error = result['error'] as String?;
        
        if (!isUserCancel && error != null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(error),
              backgroundColor: AppTheme.dangerColor,
            ),
          );
        }
        // Si el usuario canceló, no mostrar ningún mensaje (comportamiento normal)
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
                widget.appTitle,
                style: AppTheme.h1Style.copyWith(
                  color: AppTheme.dark,
                  fontSize: 28,
                ),
                textAlign: TextAlign.center,
              ),
              
              const SizedBox(height: 8),
              
              Text(
                widget.appSubtitle,
                style: AppTheme.subTitleStyle.copyWith(fontSize: 16),
                textAlign: TextAlign.center,
              ),
              
              const SizedBox(height: 48),
              
              // Información sobre biometría
              if (_biometricAvailable && _biometricType.isNotEmpty) ...[
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
                          widget.biometricAvailableText ?? 
                              '$_biometricType configurada y lista para usar',
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
                              widget.authenticatingText ?? 'Autenticando...',
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
                                  : (widget.biometricUnavailableButtonText ?? 'Biometría no disponible'),
                              style: AppTheme.h5Style.copyWith(color: Colors.white),
                            ),
                          ],
                        ),
                ),
              ),
              
              const SizedBox(height: 32),
              
              // Botón de registro (solo si se proporciona callback)
              if (widget.onNavigateToSignup != null)
                TextButton(
                  style: TextButton.styleFrom(
                    foregroundColor: AppTheme.primaryColor,
                  ),
                  child: Text(
                    widget.signupButtonText ?? '¿No tienes cuenta? Regístrate aquí',
                    style: AppTheme.subTitleStyle.copyWith(
                      color: AppTheme.primaryColor,
                      fontSize: 14,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                  onPressed: () {
                    if (widget.onNavigateToSignup != null) {
                      widget.onNavigateToSignup!(context);
                    }
                  },
                ),
              
              // Botón para ir al inicio sin registrarse (solo si se proporciona callback)
              if (widget.onNavigateToHome != null) ...[
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      if (widget.onNavigateToHome != null) {
                        widget.onNavigateToHome!(context);
                      }
                    },
                    icon: Icon(Icons.home, size: 22),
                    label: Text(
                      widget.goToHomeButtonText ?? 'Ir al inicio de la app',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.infoColor,
                      foregroundColor: Colors.white,
                      padding: EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      elevation: 3,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

