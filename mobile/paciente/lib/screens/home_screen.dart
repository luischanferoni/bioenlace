import 'package:flutter/material.dart';

import '../services/turnos_service.dart';
import '../theme/paciente_theme_extensions.dart';
import 'chat_motivos_screen.dart';
import 'mis_turnos_screen.dart';

/// Pantalla de inicio del paciente: saludo, próximo turno y acciones rápidas.
class HomeScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final VoidCallback onIrAChat;

  const HomeScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    required this.onIrAChat,
  }) : super(key: key);

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late TurnosService _turnosService;
  List<dynamic> _turnos = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _turnosService = TurnosService(authToken: widget.authToken);
    _loadTurnos();
  }

  Future<void> _loadTurnos() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _turnosService.getMisTurnos();
    setState(() {
      _loading = false;
      _turnos = result['turnos'] ?? [];
      if (result['success'] != true) _error = result['message'] as String?;
    });
  }

  /// Primer turno futuro (fecha/hora >= ahora) o el primero de la lista.
  Map<String, dynamic>? _getProximoTurno() {
    if (_turnos.isEmpty) return null;
    final now = DateTime.now();
    for (final t in _turnos) {
      final map = t as Map<String, dynamic>;
      final fecha = map['fecha']?.toString();
      final hora = map['hora']?.toString();
      if (fecha != null && hora != null) {
        try {
          final dt = DateTime.parse('$fecha $hora');
          if (dt.isAfter(now) || dt.isAtSameMomentAs(now)) return map;
        } catch (_) {}
      }
    }
    return _turnos.isNotEmpty ? _turnos.first as Map<String, dynamic> : null;
  }

  String _saludo() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    final proximo = _getProximoTurno();
    final idConsulta = proximo != null ? proximo['id_consulta'] : null;
    final puedeCargarMotivos = idConsulta != null;

    return Scaffold(
      backgroundColor: cs.surface,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadTurnos,
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24.0),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, textAlign: TextAlign.center),
                            const SizedBox(height: 16),
                            ElevatedButton(
                              onPressed: _loadTurnos,
                              child: const Text('Reintentar'),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
                      children: [
                        Text(
                          '${_saludo()}, ${widget.userName.split(',').first.trim()}',
                          style: tt.headlineSmall?.copyWith(
                            color: cs.onSurface,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '¿En qué podemos ayudarte?',
                          style: tt.bodyLarge?.copyWith(
                            color: cs.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 24),
                        if (proximo != null) ...[
                          _buildCardProximoTurno(context, proximo, puedeCargarMotivos),
                          const SizedBox(height: 24),
                        ] else
                          _buildCardSinTurnos(context),
                        const SizedBox(height: 16),
                        _buildSeccionAcciones(context, puedeCargarMotivos, idConsulta),
                      ],
                    ),
        ),
      ),
    );
  }

  Widget _buildCardProximoTurno(
    BuildContext context,
    Map<String, dynamic> t,
    bool puedeCargarMotivos,
  ) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    final idConsulta = t['id_consulta'];
    return Card(
      elevation: 0,
      color: cs.primary.withValues(alpha: 0.1),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.calendar_today, color: cs.primary, size: 24),
                const SizedBox(width: 10),
                Text(
                  'Tu próximo turno',
                  style: tt.titleSmall?.copyWith(
                    color: cs.primary,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              '${t['fecha']} · ${t['hora']}',
              style: tt.titleMedium,
            ),
            if (t['servicio'] != null)
              Text(
                t['servicio'].toString(),
                style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
              ),
            if (t['profesional'] != null)
              Text(
                'Con: ${t['profesional']}',
                style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
              ),
            if (puedeCargarMotivos) ...[
              const SizedBox(height: 12),
              OutlinedButton.icon(
                icon: const Icon(Icons.edit_note, size: 18),
                label: const Text('Cargar motivos de la consulta'),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ChatMotivosScreen(
                        consultaId: idConsulta as int,
                        authToken: widget.authToken,
                        userId: widget.userId,
                        userName: widget.userName,
                        titulo: 'Motivos · ${t['fecha']} ${t['hora']}',
                      ),
                    ),
                  );
                },
                style: OutlinedButton.styleFrom(
                  foregroundColor: cs.primary,
                  side: BorderSide(color: cs.primary),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildCardSinTurnos(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    return Card(
      elevation: 0,
      color: cs.surfaceContainerHighest,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.event_available, color: cs.onSurfaceVariant),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'No tenés turnos pendientes. Podés pedir uno desde el chat.',
                style: tt.bodyMedium?.copyWith(color: cs.onSurfaceVariant),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSeccionAcciones(
    BuildContext context,
    bool puedeCargarMotivos,
    dynamic idConsulta,
  ) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Acciones rápidas',
          style: tt.titleSmall?.copyWith(
            color: cs.primary,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            _buildActionChip(
              context,
              icon: Icons.add_circle_outline,
              label: 'Pedir turno',
              onTap: widget.onIrAChat,
            ),
            _buildActionChip(
              context,
              icon: Icons.calendar_today,
              label: 'Ver mis turnos',
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => MisTurnosScreen(
                      authToken: widget.authToken,
                      userId: widget.userId,
                      userName: widget.userName,
                    ),
                  ),
                );
              },
            ),
            if (puedeCargarMotivos && idConsulta != null)
              _buildActionChip(
                context,
                icon: Icons.edit_note,
                label: 'Cargar motivos',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ChatMotivosScreen(
                        consultaId: idConsulta as int,
                        authToken: widget.authToken,
                        userId: widget.userId,
                        userName: widget.userName,
                        titulo: 'Motivos de la consulta',
                      ),
                    ),
                  );
                },
              ),
          ],
        ),
      ],
    );
  }

  Widget _buildActionChip(
    BuildContext context, {
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    return Material(
      color: cs.primary.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 20, color: cs.primary),
              const SizedBox(width: 8),
              Text(
                label,
                style: tt.labelLarge?.copyWith(
                  color: cs.primary,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
