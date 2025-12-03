// lib/main.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import 'screens/main_screen.dart';
import 'screens/config_wizard_screen.dart';

// Clave global para el Navigator
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

Future<Map<String, dynamic>> _getUserData() async {
  final prefs = await SharedPreferences.getInstance();
  return {
    'authToken': prefs.getString('auth_token'),
    'rrhhId': prefs.getString('rrhh_id') ?? '7830',
    'configCompleted': prefs.getBool('config_completed') ?? false,
  };
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  final prefs = await SharedPreferences.getInstance();
  
  // Simulación de usuario: id_rrhh=7830, id_user=5748
  // Guardar estos valores si no existen
  if (!prefs.containsKey('user_id')) {
    await prefs.setString('user_id', '5748');
    await prefs.setString('rrhh_id', '7830');
    await prefs.setString('user_name', 'Usuario Médico');
    await prefs.setBool('is_logged_in', false); // No auto-login, requiere autenticación
  }
  
  final isLoggedIn = prefs.getBool('is_logged_in') ?? false;
  final userId = prefs.getString('user_id') ?? '5748';
  final userName = prefs.getString('user_name') ?? 'Usuario Médico';

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
      home: isLoggedIn && userId.isNotEmpty
          ? FutureBuilder<Map<String, dynamic>>(
              future: _getUserData(),
              builder: (context, snapshot) {
                if (!snapshot.hasData) {
                  return const Scaffold(
                    body: Center(child: CircularProgressIndicator()),
                  );
                }
                final data = snapshot.data!;
                final authToken = data['authToken'] as String?;
                final configCompleted = data['configCompleted'] as bool? ?? false;
                
                // Si no hay token o no está configurado, mostrar login
                if (authToken == null || authToken.isEmpty) {
                  return LoginScreen(
                    appTitle: 'Bienvenido a BioEnlace Médico',
                    appSubtitle: 'Tu plataforma de gestión médica',
                    onLoginSuccess: (userId, userName) async {
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setBool('is_logged_in', true);
                      await prefs.setString('user_id', userId);
                      await prefs.setString('user_name', userName);
                      
                      navigatorKey.currentState?.pushReplacement(
                        MaterialPageRoute(
                          builder: (_) => FutureBuilder<Map<String, dynamic>>(
                            future: _getUserData(),
                            builder: (context, snapshot) {
                              if (!snapshot.hasData) {
                                return const Scaffold(
                                  body: Center(child: CircularProgressIndicator()),
                                );
                              }
                              final loginData = snapshot.data!;
                              final loginToken = loginData['authToken'] as String?;
                              final loginConfigCompleted = loginData['configCompleted'] as bool? ?? false;
                              
                              if (loginToken == null || loginToken.isEmpty) {
                                return LoginScreen(
                                  appTitle: 'Bienvenido a BioEnlace Médico',
                                  appSubtitle: 'Tu plataforma de gestión médica',
                                  onLoginSuccess: (_, __) {},
                                );
                              }
                              
                              if (!loginConfigCompleted) {
                                return ConfigWizardScreen(
                                  userId: userId,
                                  userName: userName,
                                  authToken: loginToken,
                                );
                              }
                              
                              return MainScreen(
                                userId: userId,
                                userName: userName,
                                authToken: loginToken,
                                rrhhId: loginData['rrhhId'] as String? ?? '7830',
                              );
                            },
                          ),
                        ),
                      );
                    },
                  );
                }
                
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
              appTitle: 'Bienvenido a BioEnlace Médico',
              appSubtitle: 'Tu plataforma de gestión médica',
              onLoginSuccess: (userId, userName) async {
                // Guardar estado de login
                final prefs = await SharedPreferences.getInstance();
                await prefs.setBool('is_logged_in', true);
                await prefs.setString('user_id', userId);
                await prefs.setString('user_name', userName);
                
                // Usar navigatorKey para navegar desde cualquier contexto
                navigatorKey.currentState?.pushReplacement(
                  MaterialPageRoute(
                    builder: (_) => MainScreen(
                      userId: userId,
                      userName: userName,
                      authToken: prefs.getString('auth_token'),
                      rrhhId: prefs.getString('rrhh_id') ?? '7830',
                    ),
                  ),
                );
              },
            ),
    );
  }
}
