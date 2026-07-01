// lib/main.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import 'firebase/firebase_bootstrap.dart';
import 'services/chat_service.dart';
import 'screens/main_screen.dart';
import 'screens/signup_screen.dart';

void main() {
  runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();
    await FirebaseBootstrap.ensureInitialized();
    await bootstrapCarePlanReminders();

    final prefs = await SharedPreferences.getInstance();
    final isLoggedIn = prefs.getBool('is_logged_in') ?? false;
    final userId = prefs.getString('user_id') ?? '';
    final userName = prefs.getString('user_name') ?? '';
    final authToken = prefs.getString('auth_token');

    if (isLoggedIn && userId.isNotEmpty) {
      await CrashlyticsBootstrap.setUserId(userId);
    }
    ClientDiagnosticApi.bindSession(
      authToken: authToken,
      appClient: 'paciente-flutter',
    );

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
  }, (error, stack) {
    unawaited(CrashlyticsBootstrap.recordError(error, stack, fatal: true));
  });
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
          ? BiometricSessionLockScope(
              appTitle: 'BioEnlace Paciente',
              child: MainScreen(chatService: chatService!, authToken: authToken),
            )
          : LoginScreen(
              appTitle: 'Bienvenido a BioEnlace',
              appSubtitle: 'Tu asistente de salud personal',
              // Textos personalizados para la app del paciente
              welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
              signupButtonText: '¿No tienes cuenta? Regístrate aquí',
              // Login paciente: huella del teléfono. Didit solo en el registro (KYC).
              diditBiometricWorkflowId: null,
              onLoginSuccess: (userId, userName, loginContext) async {
                await CrashlyticsBootstrap.setUserId(userId);
                final prefs = await SharedPreferences.getInstance();
                final token = prefs.getString('auth_token');
                ClientDiagnosticApi.bindSession(
                  authToken: token,
                  appClient: 'paciente-flutter',
                );
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
            ),
    );
  }
}