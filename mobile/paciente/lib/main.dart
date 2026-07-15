// lib/main.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import 'firebase/firebase_bootstrap.dart';
import 'auth/paciente_authenticated_shell.dart';
import 'auth/paciente_post_login.dart';
import 'auth/paciente_session_prefs.dart';
import 'services/chat_service.dart';
import 'screens/main_screen.dart';

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

void main() {
  runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();
    await FirebaseBootstrap.ensureInitialized();
    await bootstrapCarePlanReminders();

    final prefs = await SharedPreferences.getInstance();
    await PacienteSessionPrefs.reconcileStaleSessionOnLaunch();

    var isLoggedIn = await PacienteSessionPrefs.hasRestorableSession();
    var userId = prefs.getString('user_id') ?? '';
    var userName = prefs.getString('user_name') ?? '';
    var authToken = prefs.getString('auth_token');

    if (isLoggedIn && authToken != null && authToken.isNotEmpty) {
      // Renovación preventiva si el JWT está cerca de expirar.
      authToken = await BearerSessionAuth.ensureFreshBearerToken(
        authToken,
        appClient: BearerSessionAuth.appClientPaciente,
      );
      final check = await BearerSessionAuth.checkBearerToken(
        authToken,
        appClient: BearerSessionAuth.appClientPaciente,
      );
      if (check == BearerSessionCheckResult.invalid) {
        await PacienteSessionPrefs.clearInvalidAuthSession();
        isLoggedIn = false;
        userId = '';
        userName = '';
        authToken = null;
      }
      // networkError: conservar sesión local; el próximo request real decidirá.
    }

    if (isLoggedIn && userId.isNotEmpty) {
      await CrashlyticsBootstrap.setUserId(userId);
    }
    ClientDiagnosticApi.bindSession(
      authToken: authToken,
      appClient: BearerSessionAuth.appClientPaciente,
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
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      navigatorKey: navigatorKey,
      home: isLoggedIn
          ? PacienteBiometricGate(
              child: wrapPacienteAuthenticatedShell(
                child: MainScreen(
                  chatService: chatService!,
                  authToken: authToken,
                ),
              ),
            )
          : buildPacienteLoginScreen(
              onLoginSuccess: navigatePacienteAfterLogin,
            ),
    );
  }
}
