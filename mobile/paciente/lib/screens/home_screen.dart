import 'package:flutter/material.dart';

import '../services/turnos_service.dart';
import '../theme/paciente_theme_extensions.dart';
import 'chat_motivos_screen.dart';

/// Pantalla de inicio del paciente: saludo y próximo turno.
class HomeScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;

  const HomeScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
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

  /// Quita el documento al final (`"Apellido, Nombre - 12345678"` → `"Apellido, Nombre"`).
  String _profesionalSinDni(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    final s = raw.trim();
    const sep = ' - ';
    final i = s.lastIndexOf(sep);
    if (i <= 0) return s;
    final tail = s.substring(i + sep.length).trim();
    if (RegExp(r'^[0-9.\-\s]+$').hasMatch(tail)) {
      return s.substring(0, i).trim();
    }
    return s;
  }

  static const _weekdaysEs = [
    'domingo',
    'lunes',
    'martes',
    'miércoles',
    'jueves',
    'viernes',
    'sábado',
  ];

  /// Fecha relativa / día en español (misma idea que slots en API).
  String _fechaAmigable(String? fechaYmd) {
    if (fechaYmd == null || fechaYmd.isEmpty) return '';
    final parts = fechaYmd.split('-');
    if (parts.length != 3) return fechaYmd;
    final y = int.tryParse(parts[0]);
    final mo = int.tryParse(parts[1]);
    final d = int.tryParse(parts[2]);
    if (y == null || mo == null || d == null) return fechaYmd;
    final slot = DateTime(y, mo, d);
    final today = DateTime.now();
    final t0 = DateTime(today.year, today.month, today.day);
    final s0 = DateTime(slot.year, slot.month, slot.day);
    final diffDays = s0.difference(t0).inDays;
    if (diffDays == 0) return 'Hoy';
    if (diffDays == 1) return 'Mañana';
    if (diffDays == 2) return 'Pasado mañana';
    final name = _weekdaysEs[slot.weekday % 7];
    return '$name ${d.toString().padLeft(2, '0')}/${mo.toString().padLeft(2, '0')}';
  }

  /// Hora `HH:mm` sin segundos (p. ej. `08:30:00` → `08:30`).
  String _horaSinSegundos(String? hora) {
    if (hora == null || hora.trim().isEmpty) return '';
    final t = hora.trim();
    final idx = t.indexOf(':');
    if (idx < 0) return t;
    final hStr = t.substring(0, idx);
    final rest = t.substring(idx + 1);
    final idx2 = rest.indexOf(':');
    final minStr = idx2 >= 0 ? rest.substring(0, idx2) : rest;
    final h = int.tryParse(hStr);
    final m = int.tryParse(minStr);
    if (h != null && m != null) {
      return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
    }
    if (t.length >= 5) return t.substring(0, 5);
    return t;
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
              '${_fechaAmigable(t['fecha']?.toString())} · ${_horaSinSegundos(t['hora']?.toString())}',
              style: tt.titleMedium,
            ),
            if (t['servicio'] != null)
              Text(
                t['servicio'].toString(),
                style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
              ),
            if (t['profesional'] != null)
              Text(
                'Con: ${_profesionalSinDni(t['profesional']?.toString())}',
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
                        titulo: 'Motivos · ${_fechaAmigable(t['fecha']?.toString())} · ${_horaSinSegundos(t['hora']?.toString())}',
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

}
