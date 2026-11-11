import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Aprueba el ingreso a la web que pidió otro dispositivo.
///
/// Se abre desde el enlace profundo `bioenlace-personalsalud://qr-login/<challenge>`
/// que muestra la página intermedia del QR. La sesión de la app ya pasa por el gate
/// biométrico local, así que confirmar acá equivale a desbloquear con huella o rostro.
class QrLoginApproveScreen extends StatefulWidget {
  const QrLoginApproveScreen({super.key, required this.challengeId});

  final String challengeId;

  @override
  State<QrLoginApproveScreen> createState() => _QrLoginApproveScreenState();
}

enum _Step { preparing, noSession, ready, sending, approved, failed }

class _QrLoginApproveScreenState extends State<QrLoginApproveScreen> {
  _Step _step = _Step.preparing;
  String _error = '';
  String _token = '';

  @override
  void initState() {
    super.initState();
    unawaited(_loadSession());
  }

  Future<void> _loadSession() async {
    String token = '';
    try {
      token = (await AuthTokenStore.instance.read()) ?? '';
    } catch (_) {
      token = '';
    }
    if (!mounted) {
      return;
    }
    setState(() {
      _token = token.trim();
      _step = _token.isEmpty ? _Step.noSession : _Step.ready;
    });
  }

  Future<void> _approve() async {
    setState(() {
      _step = _Step.sending;
      _error = '';
    });

    try {
      await ApiClient(
        authToken: _token,
        appClient: 'bioenlace-personalsalud',
        traceLabel: 'auth/qr-login-aprobar',
      ).postData(
        '/auth/qr-login-aprobar',
        body: {'challenge_id': widget.challengeId},
      );

      if (!mounted) {
        return;
      }
      setState(() => _step = _Step.approved);
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _step = _Step.failed;
        _error = error
            .toString()
            .replaceFirst('Exception: ', '')
            .replaceFirst('ApiContractException: ', '');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Ingreso a la web')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Center(child: _buildBody()),
        ),
      ),
    );
  }

  Widget _buildBody() {
    switch (_step) {
      case _Step.preparing:
        return const CircularProgressIndicator();

      case _Step.noSession:
        return _Message(
          icon: Icons.lock_outline,
          title: 'Iniciá sesión en la app',
          body:
              'Para aprobar el ingreso a la web primero ingresá a Bioenlace Personal '
              'de Salud en este teléfono.',
          actionLabel: 'Cerrar',
          onAction: () => Navigator.of(context).maybePop(),
        );

      case _Step.ready:
        return _Message(
          icon: Icons.phonelink_lock,
          title: '¿Aprobás este ingreso?',
          body:
              'Se pidió ingresar a Bioenlace desde otra computadora. Si no fuiste vos, '
              'cancelá y cambiá tu acceso.',
          actionLabel: 'Confirmar ingreso',
          onAction: _approve,
          secondaryLabel: 'Cancelar',
          onSecondary: () => Navigator.of(context).maybePop(),
        );

      case _Step.sending:
        return const _Message(
          icon: Icons.hourglass_top,
          title: 'Aprobando…',
          body: 'Estamos confirmando el ingreso en la computadora.',
        );

      case _Step.approved:
        return _Message(
          icon: Icons.check_circle_outline,
          title: 'Ingreso aprobado',
          body: 'Ya podés continuar en la computadora.',
          actionLabel: 'Listo',
          onAction: () => Navigator.of(context).maybePop(),
        );

      case _Step.failed:
        return _Message(
          icon: Icons.error_outline,
          title: 'No pudimos aprobar el ingreso',
          body: _error.isEmpty
              ? 'El pedido puede haber vencido. Volvé a la computadora y escaneá el código otra vez.'
              : _error,
          actionLabel: 'Reintentar',
          onAction: _approve,
          secondaryLabel: 'Cerrar',
          onSecondary: () => Navigator.of(context).maybePop(),
        );
    }
  }
}

class _Message extends StatelessWidget {
  const _Message({
    required this.icon,
    required this.title,
    required this.body,
    this.actionLabel,
    this.onAction,
    this.secondaryLabel,
    this.onSecondary,
  });

  final IconData icon;
  final String title;
  final String body;
  final String? actionLabel;
  final VoidCallback? onAction;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 56),
        const SizedBox(height: 16),
        Text(
          title,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 12),
        Text(body, textAlign: TextAlign.center),
        const SizedBox(height: 24),
        if (actionLabel != null && onAction != null)
          SizedBox(
            width: double.infinity,
            child: FilledButton(onPressed: onAction, child: Text(actionLabel!)),
          ),
        if (secondaryLabel != null && onSecondary != null)
          TextButton(onPressed: onSecondary, child: Text(secondaryLabel!)),
      ],
    );
  }
}
