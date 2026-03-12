import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/medico_registration_service.dart';

/// Pantalla de registro/verificación KYC para médicos usando Didit + backend (REPEFS/MPI).
class MedicoSignupScreen extends StatefulWidget {
  const MedicoSignupScreen({super.key});

  @override
  State<MedicoSignupScreen> createState() => _MedicoSignupScreenState();
}

class _MedicoSignupScreenState extends State<MedicoSignupScreen> {
  final MedicoRegistrationService _registrationService =
      MedicoRegistrationService();
  bool _isSubmitting = false;

  Future<void> _startRegistration() async {
    if (_isSubmitting) return;

    setState(() {
      _isSubmitting = true;
    });

    try {
      final result = await _registrationService.registerMedico();

      if (!mounted) return;

      if (result['success'] == true) {
        final registro =
            (result['data']?['registro']?['data']?['persona']) ?? {};
        final nombre =
            '${registro['nombre'] ?? ''} ${registro['apellido'] ?? ''}'.trim();

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              nombre.isNotEmpty
                  ? 'Verificación completada para $nombre'
                  : 'Verificación de médico completada correctamente',
            ),
            backgroundColor: AppTheme.successColor,
            duration: const Duration(seconds: 4),
          ),
        );

        // Volver al login para que luego pueda usar "Ya tengo cuenta"
        Navigator.pop(context);
      } else {
        final message =
            result['message'] ?? 'Error en el registro/verificación del médico';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(message),
            backgroundColor: AppTheme.dangerColor,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error inesperado: ${e.toString()}'),
          backgroundColor: AppTheme.dangerColor,
          duration: const Duration(seconds: 5),
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AppTheme.backgroundColor,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppTheme.dark),
        title: const Text(
          'Registro de Médico',
          style: TextStyle(color: AppTheme.dark),
        ),
      ),
      backgroundColor: AppTheme.backgroundColor,
      body: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 16),
            Text(
              'Verificación de identidad',
              style: AppTheme.h2Style.copyWith(color: AppTheme.dark),
            ),
            const SizedBox(height: 8),
            Text(
              'Vamos a verificar tu identidad como profesional de la salud '
              'usando Didit y REFEPS. Este proceso utiliza tu documento, selfie '
              'y validaciones externas para garantizar que eres un médico habilitado.',
              style: AppTheme.subTitleStyle,
            ),
            const SizedBox(height: 32),
            Center(
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.08),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.medical_information,
                  size: 64,
                  color: AppTheme.primaryColor,
                ),
              ),
            ),
            const SizedBox(height: 32),
            Text(
              'Requisitos:',
              style: AppTheme.h5Style.copyWith(color: AppTheme.dark),
            ),
            const SizedBox(height: 8),
            _buildBullet(
                'Tu documento de identidad vigente (DNI, pasaporte, etc.).'),
            _buildBullet(
                'Un lugar bien iluminado para tomar la selfie y prueba de vida.'),
            _buildBullet(
                'Al finalizar, validaremos tu matrícula contra REFEPS/SISA.'),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              height: 56,
              child: ElevatedButton(
                style: ButtonStyles.primary(context),
                onPressed: _isSubmitting ? null : _startRegistration,
                child: _isSubmitting
                    ? Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor:
                                  AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Text(
                            'Iniciando verificación...',
                            style: AppTheme.h5Style
                                .copyWith(color: Colors.white),
                          ),
                        ],
                      )
                    : Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.verified_user, size: 24),
                          const SizedBox(width: 12),
                          Text(
                            'Iniciar verificación de médico',
                            style: AppTheme.h5Style
                                .copyWith(color: Colors.white),
                          ),
                        ],
                      ),
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildBullet(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4.0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('• ',
              style: TextStyle(color: AppTheme.dark, fontSize: 14)),
          Expanded(
            child: Text(
              text,
              style: AppTheme.subTitleStyle,
            ),
          ),
        ],
      ),
    );
  }
}

