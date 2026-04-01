// lib/main.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart'; // Usar LoginScreen del paquete compartido

import 'services/chat_service.dart';
import 'screens/main_screen.dart';
import 'screens/signup_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final prefs = await SharedPreferences.getInstance();
  final isLoggedIn = prefs.getBool('is_logged_in') ?? false;
  final userId = prefs.getString('user_id') ?? '';
  final userName = prefs.getString('user_name') ?? '';
  final authToken = prefs.getString('auth_token');

  ChatService? chatService;

  if (isLoggedIn) {
    chatService = ChatService(
      currentUserId: userId,
      currentUserName: userName,
    );
  }

  runApp(MyApp(
    isLoggedIn: isLoggedIn,
    chatService: chatService,
    authToken: authToken,
  ));
}

class MyApp extends StatelessWidget {
  final bool isLoggedIn;
  final ChatService? chatService;
  final String? authToken;

  const MyApp({Key? key, required this.isLoggedIn, this.chatService, this.authToken}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'BioEnlace Paciente',
      theme: AppTheme.lightTheme,
      home: isLoggedIn
          ? MainScreen(chatService: chatService!, authToken: authToken)
          : LoginScreen(
              appTitle: 'Bienvenido a BioEnlace',
              appSubtitle: 'Tu asistente de salud personal',
              // Textos personalizados para la app del paciente
              welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
              signupButtonText: '¿No tienes cuenta? Regístrate aquí',
              goToHomeButtonText: 'Ir al inicio de la app',
              diditBiometricWorkflowId: AppConfig.diditPacienteBiometricWorkflowId,
              onSimulateCreatePaciente: (loginContext) async {
                try {
                  final uri = Uri.parse(
                    '${AppConfig.apiUrl}/registro/simular-paciente-mercedes',
                  );
                  final response = await http
                      .post(uri)
                      .timeout(Duration(seconds: 15));

                  final data = json.decode(response.body);
                  if (response.statusCode >= 200 &&
                      response.statusCode < 300 &&
                      data['success'] == true) {
                    ScaffoldMessenger.of(loginContext).showSnackBar(
                      SnackBar(
                        content: Text(
                          'Paciente de prueba "Mercedes Diaz" (DNI 29558371) creado/actualizado.',
                        ),
                        backgroundColor: AppTheme.successColor,
                      ),
                    );
                  } else {
                    final message = data['message'] ??
                        'No se pudo crear el paciente simulado.';
                    ScaffoldMessenger.of(loginContext).showSnackBar(
                      SnackBar(
                        content: Text(message),
                        backgroundColor: AppTheme.dangerColor,
                      ),
                    );
                  }
                } catch (e) {
                  ScaffoldMessenger.of(loginContext).showSnackBar(
                    SnackBar(
                      content: Text(
                        'Error al crear paciente simulado: ${e.toString()}',
                      ),
                      backgroundColor: AppTheme.dangerColor,
                    ),
                  );
                }
              },
              onLoginSuccess: (userId, userName, loginContext) async {
                // Token puede haber sido guardado por el flujo de login (ej. biometría)
                final prefs = await SharedPreferences.getInstance();
                final token = prefs.getString('auth_token');
                final newChatService = ChatService(
                  currentUserId: userId,
                  currentUserName: userName,
                );
                if (!loginContext.mounted) return;
                Navigator.pushReplacement(
                  loginContext,
                  MaterialPageRoute(
                    builder: (_) => MainScreen(chatService: newChatService, authToken: token),
                  ),
                );
              },
              onNavigateToSignup: (loginContext) {
                Navigator.push(
                  loginContext,
                  MaterialPageRoute(builder: (_) => SignupScreen()),
                );
              },
              onNavigateToHome: (loginContext) async {
                // Mostrar indicador de carga
                showDialog(
                  context: loginContext,
                  barrierDismissible: false,
                  builder: (context) => Center(
                    child: CircularProgressIndicator(),
                  ),
                );

                try {
                  // Simular sesión: obtener token para el usuario con id 5749 (paciente de prueba)
                  final tokenResponse = await http.get(
                    Uri.parse('${AppConfig.apiUrl}/auth/generar-token-prueba?user_id=5749'),
                  ).timeout(Duration(seconds: 10));

                  if (tokenResponse.statusCode == 200) {
                    final responseData = json.decode(tokenResponse.body);
                    
                    if (responseData['success'] == true) {
                      final data = responseData['data'];
                      final token = data['token'] as String;
                      final user = data['user'] as Map<String, dynamic>;
                      final persona = data['persona'] as Map<String, dynamic>;
                      
                      // Guardar datos en SharedPreferences
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setBool('is_logged_in', true);
                      await prefs.setString('auth_token', token);
                      await prefs.setString('user_id', user['id'].toString());
                      await prefs.setString('user_name', '${persona['apellido']}, ${persona['nombre']}');
                      await prefs.setString('dni_detected', persona['documento']);
                      
                      // Cerrar el diálogo de carga
                      Navigator.pop(loginContext);
                      
                      // Crear servicio de chat con los datos del paciente real
                      final chatService = ChatService(
                        currentUserId: user['id'].toString(),
                        currentUserName: '${persona['apellido']}, ${persona['nombre']}',
                      );
                      
                      // Mostrar mensaje de éxito
                      ScaffoldMessenger.of(loginContext).showSnackBar(
                        SnackBar(
                          content: Text('Sesión iniciada como ${persona['apellido']}, ${persona['nombre']}'),
                          backgroundColor: AppTheme.successColor,
                          duration: Duration(seconds: 2),
                        ),
                      );
                      
                      // Navegar a MainScreen (Inicio con bottom nav)
                      Navigator.pushReplacement(
                        loginContext,
                        MaterialPageRoute(
                          builder: (_) => MainScreen(chatService: chatService, authToken: token),
                        ),
                      );
                    } else {
                      // Cerrar el diálogo de carga
                      Navigator.pop(loginContext);
                      
                      // Mostrar error y usar modo visitante como fallback
                      ScaffoldMessenger.of(loginContext).showSnackBar(
                        SnackBar(
                          content: Text('No se pudo obtener token. Usando modo visitante.'),
                          backgroundColor: AppTheme.warningColor,
                          duration: Duration(seconds: 3),
                        ),
                      );
                      
                      final demoChatService = ChatService(
                        currentUserId: 'visitor_${DateTime.now().millisecondsSinceEpoch}',
                        currentUserName: 'Visitante',
                      );
                      
                      Navigator.pushReplacement(
                        loginContext,
                        MaterialPageRoute(
                          builder: (_) => MainScreen(chatService: demoChatService, authToken: null),
                        ),
                      );
                    }
                  } else {
                    // Cerrar el diálogo de carga
                    Navigator.pop(loginContext);

                    // Mostrar error y usar modo visitante como fallback
                    ScaffoldMessenger.of(loginContext).showSnackBar(
                      SnackBar(
                        content: Text('Error al conectar con el servidor. Usando modo visitante.'),
                        backgroundColor: AppTheme.warningColor,
                        duration: Duration(seconds: 3),
                      ),
                    );

                    final demoChatService = ChatService(
                      currentUserId: 'visitor_${DateTime.now().millisecondsSinceEpoch}',
                      currentUserName: 'Visitante',
                    );

                    Navigator.pushReplacement(
                      loginContext,
                      MaterialPageRoute(
                        builder: (_) => MainScreen(chatService: demoChatService, authToken: null),
                      ),
                    );
                  }
                } catch (e) {
                  // Cerrar el diálogo de carga si aún está abierto
                  if (Navigator.canPop(loginContext)) {
                    Navigator.pop(loginContext);
                  }
                  
                  // Mostrar error y usar modo visitante como fallback
                  ScaffoldMessenger.of(loginContext).showSnackBar(
                    SnackBar(
                      content: Text('Error: ${e.toString()}. Usando modo visitante.'),
                      backgroundColor: AppTheme.warningColor,
                      duration: Duration(seconds: 3),
                    ),
                  );
                  
                  final demoChatService = ChatService(
                    currentUserId: 'visitor_${DateTime.now().millisecondsSinceEpoch}',
                    currentUserName: 'Visitante',
                  );

                  Navigator.pushReplacement(
                    loginContext,
                    MaterialPageRoute(
                      builder: (_) => MainScreen(chatService: demoChatService, authToken: null),
                    ),
                  );
                }
              },
            ),
    );
  }
}