// lib/main.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import 'config/paciente_dev_config.dart';
import 'auth/paciente_dev_login.dart';
import 'firebase/firebase_bootstrap.dart';
import 'services/chat_service.dart';
import 'screens/main_screen.dart';
import 'screens/signup_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await FirebaseBootstrap.ensureInitialized();
  await bootstrapCarePlanReminders();

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
      authToken: authToken,
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
              onLoginSuccess: (userId, userName, loginContext) async {
                // Token puede haber sido guardado por el flujo de login (ej. biometría)
                final prefs = await SharedPreferences.getInstance();
                final token = prefs.getString('auth_token');
                final newChatService = ChatService(
                  currentUserId: userId,
                  currentUserName: userName,
                  authToken: token,
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
              onNavigateToHome: PacienteDevConfig.showDevHomeButton
                  ? navigatePacienteDevHome
                  : null,
            ),
    );
  }
}