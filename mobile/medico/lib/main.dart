// lib/main.dart
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'screens/main_screen.dart';
import 'screens/config_wizard_screen.dart';
import 'screens/medico_signup_screen.dart';

/// Usuario de prueba para simulación en login (mismo mecanismo que paciente vía `generar-token-prueba`).
const int _kSimulacionMedicoUserId = 5748;

// Clave global para el Navigator
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

Future<Map<String, dynamic>> _getUserData() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final authToken = prefs.getString('auth_token');
    
    // Manejar rrhh_id que puede estar guardado como int o String
    String rrhhId = '7830';
    if (prefs.containsKey('rrhh_id')) {
      // Intentar leer como int primero (como se guarda en config_wizard_screen)
      final rrhhIdInt = prefs.getInt('rrhh_id');
      if (rrhhIdInt != null) {
        rrhhId = rrhhIdInt.toString();
      } else {
        // Si no es int, intentar leer como String
        final rrhhIdStr = prefs.getString('rrhh_id');
        if (rrhhIdStr != null) {
          rrhhId = rrhhIdStr;
        }
      }
    }
    
    final configCompleted = prefs.getBool('config_completed') ?? false;
    
    print('[DEBUG] _getUserData() - authToken: ${authToken != null ? "${authToken.substring(0, authToken.length > 20 ? 20 : authToken.length)}..." : "null"}');
    print('[DEBUG] _getUserData() - rrhhId: $rrhhId');
    print('[DEBUG] _getUserData() - configCompleted: $configCompleted');
    
    return {
      'authToken': authToken,
      'rrhhId': rrhhId,
      'configCompleted': configCompleted,
    };
  } catch (e) {
    print('[ERROR] _getUserData() - Error: $e');
    // Si hay error, retornar valores por defecto
    return {
      'authToken': null,
      'rrhhId': '7830',
      'configCompleted': false,
    };
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Inicializar formato de fechas localizado para español
  await initializeDateFormatting('es', null);
  
  bool isLoggedIn = false;
  String userId = '';
  String userName = 'Usuario Médico';
  
  try {
    final prefs = await SharedPreferences.getInstance();
    isLoggedIn = prefs.getBool('is_logged_in') ?? false;
    userId = prefs.getString('user_id') ?? '';
    userName = prefs.getString('user_name') ?? 'Usuario Médico';
  } catch (e) {
    // En caso de error, continuar con valores por defecto
    print('[ERROR] main() - Error al inicializar SharedPreferences: $e');
  }

  runApp(MyApp(
    isLoggedIn: isLoggedIn,
    userId: userId,
    userName: userName,
  ));
}

class MyApp extends StatelessWidget {
  final bool isLoggedIn;
  final String userId;
  final String userName;

  const MyApp({
    Key? key,
    required this.isLoggedIn,
    required this.userId,
    required this.userName,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'BioEnlace Médico',
      theme: AppTheme.lightTheme,
      navigatorKey: navigatorKey,
      home: isLoggedIn
          ? FutureBuilder<Map<String, dynamic>>(
              future: _getUserData(),
              builder: (context, snapshot) {
                // Manejar estado de conexión
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Scaffold(
                    body: Center(child: CircularProgressIndicator()),
                  );
                }
                
                // Manejar errores
                if (snapshot.hasError) {
                  return Scaffold(
                    body: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.error_outline, size: 64, color: Colors.red),
                          const SizedBox(height: 16),
                          Text('Error al cargar datos: ${snapshot.error}'),
                          const SizedBox(height: 16),
                          ElevatedButton(
                            onPressed: () {
                              // Recargar la app
                              runApp(MyApp(
                                isLoggedIn: isLoggedIn,
                                userId: userId,
                                userName: userName,
                              ));
                            },
                            child: const Text('Reintentar'),
                          ),
                        ],
                      ),
                    ),
                  );
                }
                
                if (!snapshot.hasData) {
                  return const Scaffold(
                    body: Center(child: Text('No se pudieron cargar datos de usuario')),
                  );
                }
                
                final data = snapshot.data!;
                final authToken = data['authToken'] as String?;
                final configCompleted = data['configCompleted'] as bool? ?? false;
                
                // Si la configuración no está completa, mostrar wizard
                if (!configCompleted) {
                  return ConfigWizardScreen(
                    userId: userId,
                    userName: userName,
                    authToken: authToken,
                  );
                }
                
                // Todo configurado, mostrar MainScreen
                return MainScreen(
                  userId: userId,
                  userName: userName,
                  authToken: authToken,
                  rrhhId: data['rrhhId'] as String? ?? '7830',
                );
              },
            )
          : LoginScreen(
              appTitle: 'BioEnlace Médico',
              appSubtitle: 'Acceso para profesionales de la salud',
              welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
              goToHomeButtonText: 'Ir al inicio de la app',
              biometricAvailableText: 'Biometría configurada y lista para usar',
              diditBiometricWorkflowId: AppConfig.diditMedicoBiometricWorkflowId,
              onLoginSuccess: (userId, userName, loginContext) async {
                final prefs = await SharedPreferences.getInstance();
                final token = prefs.getString('auth_token');
                if (!loginContext.mounted) return;
                Navigator.pushReplacement(
                  loginContext,
                  MaterialPageRoute(
                    builder: (_) => ConfigWizardScreen(
                      userId: userId,
                      userName: userName,
                      authToken: token,
                    ),
                  ),
                );
              },
              onNavigateToSignup: (loginContext) {
                Navigator.push(
                  loginContext,
                  MaterialPageRoute(
                    builder: (_) => const MedicoSignupScreen(),
                  ),
                );
              },
              onNavigateToHome: (loginContext) async {
                showDialog(
                  context: loginContext,
                  barrierDismissible: false,
                  builder: (context) => const Center(
                    child: CircularProgressIndicator(),
                  ),
                );

                try {
                  final tokenResponse = await http
                      .get(
                        Uri.parse(
                          '${AppConfig.apiUrl}/auth/generar-token-prueba?user_id=$_kSimulacionMedicoUserId',
                        ),
                      )
                      .timeout(const Duration(seconds: 10));

                  if (tokenResponse.statusCode == 200) {
                    final responseData =
                        json.decode(tokenResponse.body) as Map<String, dynamic>;

                    if (responseData['success'] == true) {
                      final data = responseData['data'] as Map<String, dynamic>;
                      final token = data['token'] as String;
                      final user = data['user'] as Map<String, dynamic>;
                      final persona = data['persona'] as Map<String, dynamic>;
                      final displayName =
                          '${persona['apellido']}, ${persona['nombre']}';

                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setBool('is_logged_in', true);
                      await prefs.setString('auth_token', token);
                      await prefs.setString('user_id', user['id'].toString());
                      await prefs.setString('user_name', displayName);
                      if (persona['documento'] != null) {
                        await prefs.setString(
                          'dni_detected',
                          persona['documento'].toString(),
                        );
                      }
                      // Forzar wizard con el nuevo usuario (evita rrhh/efector de otra sesión).
                      await prefs.setBool('config_completed', false);

                      if (!loginContext.mounted) return;
                      Navigator.pop(loginContext);

                      ScaffoldMessenger.of(loginContext).showSnackBar(
                        SnackBar(
                          content: Text(
                            'Sesión de prueba: ${user['name'] ?? displayName} (id $_kSimulacionMedicoUserId)',
                          ),
                          backgroundColor: AppTheme.successColor,
                          duration: const Duration(seconds: 2),
                        ),
                      );

                      Navigator.pushReplacement(
                        loginContext,
                        MaterialPageRoute(
                          builder: (_) => ConfigWizardScreen(
                            userId: user['id'].toString(),
                            userName: displayName,
                            authToken: token,
                          ),
                        ),
                      );
                      return;
                    }
                  }

                  if (loginContext.mounted) {
                    Navigator.pop(loginContext);
                    ScaffoldMessenger.of(loginContext).showSnackBar(
                      SnackBar(
                        content: const Text(
                          'No se pudo simular el acceso del médico de prueba. Revisá la API o el user_id.',
                        ),
                        backgroundColor: AppTheme.warningColor,
                        duration: const Duration(seconds: 4),
                      ),
                    );
                  }
                } catch (e) {
                  if (!loginContext.mounted) return;
                  if (Navigator.canPop(loginContext)) {
                    Navigator.pop(loginContext);
                  }
                  ScaffoldMessenger.of(loginContext).showSnackBar(
                    SnackBar(
                      content: Text('Error: $e'),
                      backgroundColor: AppTheme.dangerColor,
                      duration: const Duration(seconds: 4),
                    ),
                  );
                }
              },
            ),
    );
  }
}
