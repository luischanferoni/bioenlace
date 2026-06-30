// lib/main.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'auth/personalsalud_login_screen.dart';
import 'auth/personalsalud_post_login.dart';
import 'firebase/firebase_bootstrap.dart';
import 'screens/main_screen.dart';
import 'screens/config_wizard_screen.dart';

// Clave global para el Navigator
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

Future<Map<String, dynamic>> _getUserData() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final authToken = prefs.getString('auth_token');
    
    // Contexto PES (profesional_efector_servicio) persistido tras el wizard.
    String idProfesionalEfectorServicio = '0';
    final pesInt = prefs.getInt('id_profesional_efector_servicio');
    if (pesInt != null) {
      idProfesionalEfectorServicio = pesInt.toString();
    }
    
    // config_completed solo vale si el wizard fijó encounter_class (sesión operativa completa).
    final configCompleted = (prefs.getBool('config_completed') ?? false) &&
        ((prefs.getString('encounter_class') ?? '').isNotEmpty);
    
    print('[DEBUG] _getUserData() - authToken: ${authToken != null ? "${authToken.substring(0, authToken.length > 20 ? 20 : authToken.length)}..." : "null"}');
    print('[DEBUG] _getUserData() - idProfesionalEfectorServicio: $idProfesionalEfectorServicio');
    print('[DEBUG] _getUserData() - configCompleted: $configCompleted');
    
    return {
      'authToken': authToken,
      'idProfesionalEfectorServicio': idProfesionalEfectorServicio,
      'configCompleted': configCompleted,
    };
  } catch (e) {
    print('[ERROR] _getUserData() - Error: $e');
    // Si hay error, retornar valores por defecto
    return {
      'authToken': null,
      'idProfesionalEfectorServicio': '0',
      'configCompleted': false,
    };
  }
}

void main() {
  runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();
    await FirebaseBootstrap.ensureInitialized();

    // Inicializar formato de fechas localizado para español
    await initializeDateFormatting('es', null);

    bool isLoggedIn = false;
    String userId = '';
    String userName = 'Usuario';

    try {
      final prefs = await SharedPreferences.getInstance();
      isLoggedIn = prefs.getBool('is_logged_in') ?? false;
      userId = prefs.getString('user_id') ?? '';
      userName = prefs.getString('user_name') ?? 'Usuario';
    } catch (e, st) {
      unawaited(CrashlyticsBootstrap.recordError(e, st, reason: 'main_prefs'));
    }

    if (isLoggedIn && userId.isNotEmpty) {
      await CrashlyticsBootstrap.setUserId(userId);
    }

    runApp(MyApp(
      isLoggedIn: isLoggedIn,
      userId: userId,
      userName: userName,
    ));
  }, (error, stack) {
    unawaited(CrashlyticsBootstrap.recordError(error, stack, fatal: true));
  });
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
      title: 'Personal de Salud',
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
                  idProfesionalEfectorServicio:
                      data['idProfesionalEfectorServicio'] as String? ?? '0',
                );
              },
            )
          : buildPersonalsaludLoginScreen(
              onLoginSuccess: navigatePersonalsaludAfterLogin,
            ),
    );
  }
}
