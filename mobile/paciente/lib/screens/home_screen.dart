import 'package:flutter/material.dart';

import '../services/turnos_service.dart';
import '../theme/paciente_theme_extensions.dart';
import 'chat_motivos_screen.dart';

/// Pantalla de inicio del paciente: saludo, próximo turno y listados paginados (próximos / anteriores).
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
  static const int _pageLimit = 12;

  late TurnosService _turnosService;
  final ScrollController _scrollController = ScrollController();

  final List<Map<String, dynamic>> _pendientes = [];
  final List<Map<String, dynamic>> _pasados = [];

  int _totalPendientes = 0;
  int _totalPasados = 0;
  bool _loadingInicial = true;
  bool _loadingMasPendientes = false;
  bool _loadingMasPasados = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _turnosService = TurnosService(authToken: widget.authToken);
    _scrollController.addListener(_onScroll);
    _cargarInicial();
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    final pos = _scrollController.position;
    if (pos.maxScrollExtent - pos.pixels < 400) {
      _cargarMasPasados();
    }
  }

  bool get _hayMasPendientes => _pendientes.length < _totalPendientes;
  bool get _hayMasPasados => _pasados.length < _totalPasados;

  Future<void> _cargarInicial() async {
    setState(() {
      _loadingInicial = true;
      _error = null;
      _pendientes.clear();
      _pasados.clear();
    });
    final r1 = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: 0,
    );
    final r2 = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: 0,
    );
    if (!mounted) return;
    if (r1['success'] != true && r2['success'] != true) {
      setState(() {
        _loadingInicial = false;
        _error = (r1['message'] ?? r2['message']) as String? ?? 'Error al cargar turnos';
      });
      return;
    }
    setState(() {
      _loadingInicial = false;
      if (r1['success'] == true) {
        _pendientes.clear();
        _pendientes.addAll(_asMapList(r1['turnos']));
        _totalPendientes = r1['total'] as int? ?? _pendientes.length;
      }
      if (r2['success'] == true) {
        _pasados.clear();
        _pasados.addAll(_asMapList(r2['turnos']));
        _totalPasados = r2['total'] as int? ?? _pasados.length;
      }
      if (r1['success'] != true) {
        _error = r1['message'] as String?;
      } else if (r2['success'] != true) {
        _error = r2['message'] as String?;
      }
    });
  }

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> _cargarMasPendientes() async {
    if (_loadingMasPendientes || !_hayMasPendientes) return;
    setState(() => _loadingMasPendientes = true);
    final r = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: _pendientes.length,
    );
    if (!mounted) return;
    setState(() {
      _loadingMasPendientes = false;
      if (r['success'] == true) {
        _pendientes.addAll(_asMapList(r['turnos']));
        _totalPendientes = r['total'] as int? ?? _pendientes.length;
      }
    });
  }

  Future<void> _cargarMasPasados() async {
    if (_loadingMasPasados || !_hayMasPasados) return;
    setState(() => _loadingMasPasados = true);
    final r = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: _pasados.length,
    );
    if (!mounted) return;
    setState(() {
      _loadingMasPasados = false;
      if (r['success'] == true) {
        _pasados.addAll(_asMapList(r['turnos']));
        _totalPasados = r['total'] as int? ?? _pasados.length;
      }
    });
  }

  Map<String, dynamic>? get _proximoTurno =>
      _pendientes.isNotEmpty ? _pendientes.first : null;

  String _saludo() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }

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
    final proximo = _proximoTurno;
    final idConsulta = proximo != null ? proximo['id_consulta'] : null;
    final puedeCargarMotivos = idConsulta != null;

    return Scaffold(
      backgroundColor: cs.surface,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _cargarInicial,
          child: _loadingInicial
              ? const Center(child: CircularProgressIndicator())
              : _error != null && _pendientes.isEmpty && _pasados.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24.0),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, textAlign: TextAlign.center),
                            const SizedBox(height: 16),
                            ElevatedButton(
                              onPressed: _cargarInicial,
                              child: const Text('Reintentar'),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView(
                      controller: _scrollController,
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
                        if (_error != null && (_pendientes.isNotEmpty || _pasados.isNotEmpty))
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Text(
                              _error!,
                              style: tt.bodySmall?.copyWith(color: cs.error),
                            ),
                          ),
                        if (proximo != null) ...[
                          _buildCardProximoTurno(context, proximo, puedeCargarMotivos),
                          const SizedBox(height: 24),
                        ] else if (_pendientes.isEmpty)
                          _buildCardSinTurnos(context),
                        Text(
                          'Próximos turnos',
                          style: tt.titleSmall?.copyWith(
                            color: cs.onSurface,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 8),
                        if (_pendientes.isEmpty && proximo == null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Text(
                              'No tenés turnos próximos.',
                              style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                            ),
                          ),
                        ..._pendientes.map((t) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: _buildTurnoCompacto(context, t, futuro: true),
                            )),
                        if (_loadingMasPendientes)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 8),
                            child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))),
                          ),
                        if (_hayMasPendientes && !_loadingMasPendientes)
                          TextButton(
                            onPressed: _cargarMasPendientes,
                            child: const Text('Cargar más'),
                          ),
                        const SizedBox(height: 24),
                        Text(
                          'Turnos anteriores',
                          style: tt.titleSmall?.copyWith(
                            color: cs.onSurface,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 8),
                        if (_pasados.isEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Text(
                              'No hay turnos anteriores en tu historial.',
                              style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                            ),
                          ),
                        ..._pasados.map((t) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: _buildTurnoCompacto(context, t, futuro: false),
                            )),
                        if (_loadingMasPasados)
                          const Padding(
                            padding: EdgeInsets.symmetric(vertical: 12),
                            child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))),
                          ),
                        const SizedBox(height: 24),
                      ],
                    ),
        ),
      ),
    );
  }

  Widget _buildTurnoCompacto(
    BuildContext context,
    Map<String, dynamic> t, {
    required bool futuro,
  }) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    final estado = t['estado_label']?.toString() ?? t['estado']?.toString() ?? '';
    return Card(
      elevation: 0,
      color: futuro ? cs.surfaceContainerHighest : cs.surfaceContainerHigh,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${_fechaAmigable(t['fecha']?.toString())} · ${_horaSinSegundos(t['hora']?.toString())}',
              style: tt.titleSmall?.copyWith(fontWeight: FontWeight.w600),
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
            if (!futuro && estado.isNotEmpty)
              Text(
                estado,
                style: tt.labelSmall?.copyWith(color: cs.onSurfaceVariant),
              ),
          ],
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
