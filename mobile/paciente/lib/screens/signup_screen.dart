import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../services/chat_service.dart';
import '../services/registration_service.dart';
import 'chat_screen.dart';

class SignupScreen extends StatefulWidget {
  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _formKey = GlobalKey<FormState>();

  bool _isSubmitting = false;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSubmitting = true;
    });

    try {
      final registrationService = RegistrationService();
      final result = await registrationService.submitRegistration();

      if (result['success'] == true) {
        final data = result['data'];
        final registro = data['registro'] ?? {};
        final persona = registro['data']?['persona'] ?? {};
        final userId = 'paciente_${persona['id_persona'] ?? DateTime.now().millisecondsSinceEpoch}';

        // Guardar datos en SharedPreferences
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('is_logged_in', true);
        await prefs.setString('user_id', userId);
        await prefs.setString('user_name', '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim());
        await prefs.setString('dni_detected', persona['documento'] ?? '');
        await prefs.setString('name_detected', '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim());
        await prefs.setBool('biometric_enabled', true);

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Registro completado exitosamente'),
            backgroundColor: AppTheme.successColor,
          ),
        );

        // Crear servicio de chat
        final chatService = ChatService(
          currentUserId: userId,
          currentUserName: prefs.getString('user_name') ?? 'Usuario',
        );

        // Navegar a la pantalla de chat
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => ChatScreen(chatService: chatService)),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message'] ?? 'Error en el registro'),
            backgroundColor: AppTheme.dangerColor,
            duration: Duration(seconds: 5),
          ),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error inesperado: ${e.toString()}'),
          backgroundColor: AppTheme.dangerColor,
          duration: Duration(seconds: 5),
        ),
      );
    } finally {
      setState(() {
        _isSubmitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        iconTheme: IconThemeData(
          color: AppTheme.dark, // Change to your desired color
        ),        
        backgroundColor: AppTheme.backgroundColor,
        elevation: 0,
      ),
      body: Container(
        color: AppTheme.backgroundColor,
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Form(
            key: _formKey,
            child: ListView(              
            children: [
              // Título de la sección
              Text(
                'Completa tu registro',
                style: AppTheme.h2Style.copyWith(
                  color: Theme.of(context).primaryColor,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                'Sube las fotos requeridas para verificar tu identidad',
                style: AppTheme.subTitleStyle,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),
              
              const SizedBox(height: 32),
              
              // Botón de registro
              Container(
                width: double.infinity,
                height: 56,
                color: AppTheme.backgroundColor,
                child: ElevatedButton(
                  style: _isSubmitting 
                    ? ButtonStyles.primarySoft(context).copyWith(
                        backgroundColor: MaterialStateProperty.all(AppTheme.primaryColor.withOpacity(0.3)),
                      )
                    : ButtonStyles.primary(context),
                  onPressed: _isSubmitting ? null : () {
                    _submit();
                  },
                  child: _isSubmitting
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
                              'Registrando...',
                              style: AppTheme.h4Style.copyWith(
                                    color: Colors.white, 
                                    fontWeight: FontWeight.w700,
                                ),
                            ),
                          ],
                        )
                      : Text('Completar Registro'),
                ),
              ),
              
              const SizedBox(height: 24),
              
              // Botón de prueba para saltar registro (solo desarrollo)
              Center(
                child: TextButton(
                  onPressed: _isSubmitting ? null : _skipRegistration,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.flash_on,
                        size: 16,
                        color: AppTheme.warningColor,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'Saltar registro (solo pruebas)',
                        style: TextStyle(
                          color: AppTheme.warningColor,
                          fontSize: 14,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        ),
      ),
    );
  }

  /// Método para saltar el registro y navegar directamente al chat (solo pruebas)
  Future<void> _skipRegistration() async {
    try {
      // Crear datos de usuario de prueba
      final prefs = await SharedPreferences.getInstance();
      final testUserId = 'test_user_${DateTime.now().millisecondsSinceEpoch}';
      final testUserName = 'Usuario de Prueba';

      // Guardar datos de prueba en SharedPreferences
      await prefs.setBool('is_logged_in', true);
      await prefs.setString('user_id', testUserId);
      await prefs.setString('user_name', testUserName);
      await prefs.setString('dni_detected', '12345678');
      await prefs.setString('name_detected', testUserName);
      await prefs.setBool('biometric_enabled', false); // Deshabilitado para pruebas
      await prefs.setDouble('face_match_score', 0.95);

      // Crear servicio de chat
      final chatService = ChatService(
        currentUserId: testUserId,
        currentUserName: testUserName,
      );

      // Mostrar mensaje informativo
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Modo de prueba activado - Registro omitido'),
          backgroundColor: AppTheme.warningColor,
          duration: Duration(seconds: 3),
        ),
      );

      // Navegar a la pantalla de chat
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => ChatScreen(chatService: chatService)),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error al saltar registro: ${e.toString()}'),
          backgroundColor: AppTheme.dangerColor,
        ),
      );
    }
  }
}
