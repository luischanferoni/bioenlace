// lib/screens/acciones_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../navigation/native_screen_router.dart';

class AccionesScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;

  const AccionesScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
  }) : super(key: key);

  @override
  State<AccionesScreen> createState() => _AccionesScreenState();
}

class _AccionesScreenState extends State<AccionesScreen> {
  final TextEditingController _queryController = TextEditingController();
  bool _isLoading = false;
  String? _responseText;
  List<Map<String, dynamic>>? _actions;

  @override
  void dispose() {
    _queryController.dispose();
    super.dispose();
  }

  Future<void> _sendQuery() async {
    if (_queryController.text.trim().isEmpty) {
      return;
    }
    setState(() {
      _isLoading = true;
      _responseText = null;
      _actions = null;
    });

    // TODO: Implementar llamada a API de acciones/IA. Simulación temporal.
    await Future.delayed(const Duration(seconds: 1));

    setState(() {
      _isLoading = false;
      _responseText = 'Consulta recibida. Funcionalidad en desarrollo.';
      _actions = [];
    });
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      appBar: const BioAppBar(title: 'Acciones'),
      body: Container(
        color: tokens.paperBackground,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(BioSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text(
                'BioEnlace',
                style: BioTypography.h1.copyWith(fontWeight: FontWeight.w300),
              ),
              BioSpacing.gapH(BioSpacing.xs),
              Text(
                '¿En qué puedo ayudarte?',
                style: BioTypography.body.copyWith(color: tokens.textMuted),
              ),
              BioSpacing.gapH(BioSpacing.xl),
              BioInput(
                controller: _queryController,
                maxLines: 4,
                hint:
                    'Escribí tu consulta… Ejemplo: "Necesito buscar una persona" o "Quiero ver los reportes disponibles"',
              ),
              BioSpacing.gapH(BioSpacing.md),
              BioButton.primary(
                label: _isLoading ? 'Enviando…' : 'Enviar',
                icon: Icons.send,
                size: BioButtonSize.lg,
                fullWidth: true,
                loading: _isLoading,
                onPressed: _isLoading ? null : _sendQuery,
              ),
              if (_responseText != null || _actions != null) ...[
                BioSpacing.gapH(BioSpacing.lg),
                BioCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (_responseText != null)
                        Text(_responseText!, style: BioTypography.bodySm),
                      if (_actions != null && _actions!.isNotEmpty) ...[
                        BioSpacing.gapH(BioSpacing.md),
                        Text(
                          'Acciones disponibles:',
                          style: BioTypography.title,
                        ),
                        BioSpacing.gapH(BioSpacing.sm),
                        ..._actions!.map(
                          (action) => Padding(
                            padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                            child: ListTile(
                              leading: const Icon(Icons.arrow_forward),
                              title: Text(action['title'] ?? 'Acción'),
                              onTap: () => _handleActionTap(action),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
              BioSpacing.gapH(BioSpacing.xl),
              Text(
                'Acciones comunes',
                style: BioTypography.h3,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.md),
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 2,
                crossAxisSpacing: BioSpacing.md,
                mainAxisSpacing: BioSpacing.md,
                childAspectRatio: 1.2,
                children: [
                  _buildCommonActionCard(
                    icon: Icons.search_outlined,
                    title: 'Buscar persona',
                    intent: UiIntent.primary,
                    onTap: () => _showDevSnack(),
                  ),
                  _buildCommonActionCard(
                    icon: Icons.assessment_outlined,
                    title: 'Ver reportes',
                    intent: UiIntent.info,
                    onTap: () => _showDevSnack(),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _handleActionTap(Map<String, dynamic> action) {
    final co = action['client_open'];
    if (co is Map) {
      final kind = co['kind']?.toString();
      if (kind == 'ui_json') {
        final api = co['api'];
        final route = api is Map ? api['route']?.toString() : null;
        if (route != null && route.isNotEmpty) {
          final w = UiJsonScreen(
            apiAbsoluteUrl: resolveApiAbsoluteUrl(route),
            authToken: widget.authToken,
            appClient: 'bioenlace-medico',
            title: (action['display_name'] ?? action['title'] ?? 'Formulario')
                .toString(),
          );
          // Contrato nuevo: abrir SIEMPRE inline (modal) automáticamente.
          showModalBottomSheet(
            context: context,
            useSafeArea: true,
            isScrollControlled: true,
            builder: (_) => SizedBox(
              height: MediaQuery.of(context).size.height * 0.9,
              child: w,
            ),
          );
          return;
        }
      }
      final mobile = co['mobile'];
      final screenId =
          (mobile is Map ? mobile['screen_id'] : null) ?? co['screen_id'];
      if (kind == 'native' && screenId is String && screenId.isNotEmpty) {
        NativeScreenRouter.open(
          context,
          screenId: screenId,
          title:
              (action['display_name'] ?? action['title'] ?? 'Pantalla').toString(),
          args: Map<String, dynamic>.from(action),
        );
        return;
      }
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Acción no soportada aún')),
    );
  }

  void _showDevSnack() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Funcionalidad en desarrollo')),
    );
  }

  Widget _buildCommonActionCard({
    required IconData icon,
    required String title,
    required UiIntent intent,
    required VoidCallback onTap,
  }) {
    final palette = IntentPalette.of(intent);
    return BioCard(
      onTap: onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 44, color: palette.base),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            title,
            style: BioTypography.title,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
