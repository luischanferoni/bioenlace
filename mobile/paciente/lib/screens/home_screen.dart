import 'package:flutter/material.dart';

import '../services/turnos_service.dart';
import '../theme/paciente_theme_extensions.dart';
import '../utils/turno_resolucion_utils.dart';
import 'chat_motivos_screen.dart';

/// Próximo turno pendiente respecto al calendario local (solo fecha, sin hora).
enum _ProximidadPendiente { hoy, manana, masAdelante }

/// Pantalla de inicio del paciente: saludo y listados (próximos / anteriores) por pestañas.
class HomeScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final void Function(Map<String, dynamic> turno)? onResolverTurno;
  final VoidCallback? onOpenAlertas;
  final int alertasNoLeidas;

  const HomeScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.onResolverTurno,
    this.onOpenAlertas,
    this.alertasNoLeidas = 0,
  }) : super(key: key);

  @override
  State<HomeScreen> createState() => HomeScreenState();
}

class HomeScreenState extends State<HomeScreen> {
  static const int _pageLimit = 12;

  late TurnosService _turnosService;
  final ScrollController _scrollController = ScrollController();

  final List<Map<String, dynamic>> _pendientes = [];
  final List<Map<String, dynamic>> _enResolucion = [];
  final List<Map<String, dynamic>> _pasados = [];

  int _totalPendientes = 0;
  int _totalPasados = 0;
  bool _loadingInicial = true;
  bool _loadingMasPendientes = false;
  bool _loadingMasPasados = false;
  /// Recarga del listado al cambiar de pestaña (vuelve a pedir al backend).
  bool _refrescandoTabActivo = false;
  String? _error;

  /// 0 = próximos turnos, 1 = historial.
  int _tabTurnos = 0;

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
      if (_tabTurnos == 0) {
        _cargarMasPendientes();
      } else {
        _cargarMasPasados();
      }
    }
  }

  bool get _hayMasPendientes => _pendientes.length < _totalPendientes;
  bool get _hayMasPasados => _pasados.length < _totalPasados;

  /// Próximos unificados: primero EN_RESOLUCION, luego PENDIENTE por fecha/hora.
  List<Map<String, dynamic>> get _proximosVisibles {
    final all = <Map<String, dynamic>>[..._enResolucion, ..._pendientes];
    all.sort((a, b) {
      final ar = TurnoResolucionUtils.esEnResolucion(a);
      final br = TurnoResolucionUtils.esEnResolucion(b);
      if (ar != br) {
        return ar ? -1 : 1;
      }
      final da = _inicioTurnoLocal(a);
      final db = _inicioTurnoLocal(b);
      if (da == null && db == null) return 0;
      if (da == null) return 1;
      if (db == null) return -1;
      return da.compareTo(db);
    });
    return all;
  }

  Future<void> _cargarEnResolucion() async {
    final r = await _turnosService.getMisTurnos(
      alcance: 'en_resolucion',
      limit: 50,
      offset: 0,
    );
    if (!mounted) return;
    if (r['success'] == true) {
      setState(() {
        _enResolucion
          ..clear()
          ..addAll(_filtrarProximosLocales(_asMapList(r['turnos'])));
      });
    }
  }

  /// Primera carga: solo próximos (la pestaña Anteriores pide datos al entrar).
  /// Recarga próximos (incluye EN_RESOLUCION) tras resolver un turno en el asistente.
  Future<void> refrescarProximos() async {
    await _cargarInicial();
  }

  Future<void> _cargarInicial() async {
    setState(() {
      _loadingInicial = true;
      _error = null;
      _pendientes.clear();
      _pasados.clear();
      _totalPasados = 0;
    });
    final r1 = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: 0,
    );
    await _cargarEnResolucion();
    if (!mounted) return;
    if (r1['success'] != true) {
      setState(() {
        _loadingInicial = false;
        _error = r1['message'] as String? ?? 'Error al cargar turnos';
      });
      return;
    }
    setState(() {
      _loadingInicial = false;
      _pendientes
        ..clear()
        ..addAll(_filtrarProximosLocales(_asMapList(r1['turnos'])));
      _totalPendientes = r1['total'] as int? ?? _pendientes.length;
    });
  }

  /// Pull-to-refresh: actualiza próximos e historial.
  Future<void> _refrescoPullCompleto() async {
    setState(() {
      _error = null;
      _pendientes.clear();
      _enResolucion.clear();
      _pasados.clear();
    });
    final r1 = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: 0,
    );
    await _cargarEnResolucion();
    final r2 = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: 0,
    );
    if (!mounted) return;
    if (r1['success'] != true && r2['success'] != true) {
      setState(() {
        _error = (r1['message'] ?? r2['message']) as String? ?? 'Error al cargar turnos';
      });
      return;
    }
    setState(() {
      if (r1['success'] == true) {
        _pendientes
          ..clear()
          ..addAll(_filtrarProximosLocales(_asMapList(r1['turnos'])));
        _totalPendientes = r1['total'] as int? ?? _pendientes.length;
      }
      if (r2['success'] == true) {
        _pasados
          ..clear()
          ..addAll(_filtrarPasadosLocales(_asMapList(r2['turnos'])));
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
        _pendientes.addAll(_filtrarProximosLocales(_asMapList(r['turnos'])));
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
        _pasados.addAll(_filtrarPasadosLocales(_asMapList(r['turnos'])));
        _totalPasados = r['total'] as int? ?? _pasados.length;
      }
    });
  }

  /// Inicio combinado fecha+hora en horario local del dispositivo (alinea criterio con lo que ve el usuario).
  DateTime? _inicioTurnoLocal(Map<String, dynamic> t) {
    final fechaRaw = t['fecha'];
    String fechaStr;
    if (fechaRaw is String) {
      fechaStr = fechaRaw.trim().split('T').first;
    } else if (fechaRaw is DateTime) {
      fechaStr =
          '${fechaRaw.year.toString().padLeft(4, '0')}-${fechaRaw.month.toString().padLeft(2, '0')}-${fechaRaw.day.toString().padLeft(2, '0')}';
    } else {
      return null;
    }
    if (fechaStr.length < 10) return null;

    final horaRaw = t['hora'];
    String horaNorm;
    if (horaRaw is String && horaRaw.trim().isNotEmpty) {
      horaNorm = horaRaw.trim();
    } else {
      horaNorm = '00:00:00';
    }
    if (horaNorm.length == 5 && horaNorm.contains(':')) {
      horaNorm = '$horaNorm:00';
    }
    try {
      return DateTime.parse('${fechaStr}T$horaNorm');
    } catch (_) {
      return null;
    }
  }

  bool _inicioEsEstrictamentePasadoLocal(Map<String, dynamic> t) {
    final d = _inicioTurnoLocal(t);
    if (d == null) return false;
    return d.isBefore(DateTime.now());
  }

  bool _inicioEsProximoOLocal(Map<String, dynamic> t) {
    final d = _inicioTurnoLocal(t);
    if (d == null) return true;
    return !d.isBefore(DateTime.now());
  }

  List<Map<String, dynamic>> _filtrarPasadosLocales(List<Map<String, dynamic>> raw) {
    return raw.where(_inicioEsEstrictamentePasadoLocal).toList();
  }

  List<Map<String, dynamic>> _filtrarProximosLocales(List<Map<String, dynamic>> raw) {
    return raw.where(_inicioEsProximoOLocal).toList();
  }

  Future<void> _recargarPendientesDesdeCero() async {
    setState(() {
      _pendientes.clear();
      _enResolucion.clear();
      _totalPendientes = 0;
    });
    final r = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: 0,
    );
    await _cargarEnResolucion();
    if (!mounted) return;
    if (r['success'] == true) {
      setState(() {
        _pendientes
          ..clear()
          ..addAll(_filtrarProximosLocales(_asMapList(r['turnos'])));
        _totalPendientes = r['total'] as int? ?? _pendientes.length;
        _error = null;
      });
    } else {
      setState(() => _error = r['message'] as String?);
    }
  }

  Future<void> _recargarPasadosDesdeCero() async {
    setState(() {
      _pasados.clear();
      _totalPasados = 0;
    });
    final r = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: 0,
    );
    if (!mounted) return;
    if (r['success'] == true) {
      setState(() {
        _pasados
          ..clear()
          ..addAll(_filtrarPasadosLocales(_asMapList(r['turnos'])));
        _totalPasados = r['total'] as int? ?? _pasados.length;
        _error = null;
      });
    } else {
      setState(() => _error = r['message'] as String?);
    }
  }

  Future<void> _alCambiarTab(int nuevoTab) async {
    if (nuevoTab == _tabTurnos) return;
    setState(() {
      _tabTurnos = nuevoTab;
      _refrescandoTabActivo = true;
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
    });
    if (nuevoTab == 0) {
      await _recargarPendientesDesdeCero();
    } else {
      await _recargarPasadosDesdeCero();
    }
    if (!mounted) return;
    setState(() => _refrescandoTabActivo = false);
  }

  DateTime? _fechaTurnoSoloDia(Map<String, dynamic> t) {
    final raw = t['fecha']?.toString();
    if (raw == null || raw.isEmpty) return null;
    final parts = raw.split('-');
    if (parts.length != 3) return null;
    final y = int.tryParse(parts[0]);
    final mo = int.tryParse(parts[1]);
    final d = int.tryParse(parts[2]);
    if (y == null || mo == null || d == null) return null;
    return DateTime(y, mo, d);
  }

  _ProximidadPendiente _proximidadPendiente(Map<String, dynamic> t) {
    final slot = _fechaTurnoSoloDia(t);
    if (slot == null) return _ProximidadPendiente.masAdelante;
    final today = DateTime.now();
    final t0 = DateTime(today.year, today.month, today.day);
    final s0 = DateTime(slot.year, slot.month, slot.day);
    final diffDays = s0.difference(t0).inDays;
    if (diffDays == 0) return _ProximidadPendiente.hoy;
    if (diffDays == 1) return _ProximidadPendiente.manana;
    return _ProximidadPendiente.masAdelante;
  }

  ({Color bg, Color? border}) _coloresTarjetaProximo(
    BuildContext context,
    _ProximidadPendiente p,
  ) {
    final cs = context.pacienteColors;
    final sem = context.pacienteSemantic;
    switch (p) {
      case _ProximidadPendiente.hoy:
        return (
          bg: Color.alphaBlend(
            cs.error.withValues(alpha: 0.12),
            cs.surfaceContainerHighest,
          ),
          border: cs.error.withValues(alpha: 0.45),
        );
      case _ProximidadPendiente.manana:
        return (
          bg: Color.alphaBlend(
            cs.tertiary.withValues(alpha: 0.14),
            cs.surfaceContainerHighest,
          ),
          border: cs.tertiary.withValues(alpha: 0.4),
        );
      case _ProximidadPendiente.masAdelante:
        return (
          bg: Color.alphaBlend(
            sem.success.withValues(alpha: 0.10),
            cs.surfaceContainerHighest,
          ),
          border: sem.success.withValues(alpha: 0.35),
        );
    }
  }

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

  /// `id_consulta` en el listado de turnos (si existe, el paciente puede cargar motivos).
  int? _idConsultaDesdeTurno(Map<String, dynamic> t) {
    final raw = t['id_consulta'];
    if (raw == null) return null;
    if (raw is int) return raw > 0 ? raw : null;
    final n = int.tryParse(raw.toString());
    return n != null && n > 0 ? n : null;
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

  Widget _leyendaProximidad(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    final sem = context.pacienteSemantic;
    Widget dot(Color c) => Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(
            color: c,
            shape: BoxShape.circle,
          ),
        );
    Widget item(Color c, String label) => Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            dot(c),
            const SizedBox(width: 6),
            Text(label, style: tt.labelSmall?.copyWith(color: cs.onSurfaceVariant)),
          ],
        );
    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 4),
      child: Wrap(
        spacing: 16,
        runSpacing: 6,
        children: [
          item(cs.error, 'Hoy'),
          item(cs.tertiary, 'Mañana'),
          item(sem.success, 'Más adelante'),
          item(cs.secondary, 'Requiere acción'),
        ],
      ),
    );
  }

  ({Color bg, Color? border}) _coloresTarjetaEnResolucion(BuildContext context) {
    final cs = context.pacienteColors;
    return (
      bg: Color.alphaBlend(
        cs.secondary.withValues(alpha: 0.22),
        cs.surfaceContainerHighest,
      ),
      border: cs.secondary.withValues(alpha: 0.65),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;

    return Scaffold(
      backgroundColor: cs.surface,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refrescoPullCompleto,
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
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Text(
                                '${_saludo()}, ${widget.userName.split(',').first.trim()}',
                                style: tt.headlineSmall?.copyWith(
                                  color: cs.onSurface,
                                ),
                              ),
                            ),
                            if (widget.onOpenAlertas != null)
                              IconButton(
                                tooltip: 'Alertas',
                                onPressed: widget.onOpenAlertas,
                                icon: Badge(
                                  isLabelVisible: widget.alertasNoLeidas > 0,
                                  label: Text(
                                    widget.alertasNoLeidas > 99
                                        ? '99+'
                                        : '${widget.alertasNoLeidas}',
                                  ),
                                  child: const Icon(Icons.notifications_outlined),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 24),
                        if (_error != null &&
                            (_proximosVisibles.isNotEmpty || _pasados.isNotEmpty))
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Text(
                              _error!,
                              style: tt.bodySmall?.copyWith(color: cs.error),
                            ),
                          ),
                        if (_proximosVisibles.isEmpty) ...[
                          _buildCardSinTurnos(context),
                          const SizedBox(height: 20),
                        ],
                        if (_refrescandoTabActivo)
                          const Padding(
                            padding: EdgeInsets.only(bottom: 8),
                            child: LinearProgressIndicator(minHeight: 3),
                          ),
                        SegmentedButton<int>(
                          segments: const [
                            ButtonSegment<int>(
                              value: 0,
                              label: Text('Próximos'),
                              icon: Icon(Icons.event_outlined, size: 18),
                            ),
                            ButtonSegment<int>(
                              value: 1,
                              label: Text('Anteriores'),
                              icon: Icon(Icons.history, size: 18),
                            ),
                          ],
                          selected: {_tabTurnos},
                          onSelectionChanged: (Set<int> next) {
                            final n = next.first;
                            _alCambiarTab(n);
                          },
                          showSelectedIcon: false,
                        ),
                        if (_tabTurnos == 0) ...[
                          _leyendaProximidad(context),
                          const SizedBox(height: 8),
                          if (_refrescandoTabActivo && _proximosVisibles.isEmpty)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 24),
                              child: Center(child: CircularProgressIndicator()),
                            ),
                          ..._proximosVisibles.map((t) {
                            final enRes = TurnoResolucionUtils.esEnResolucion(t);
                            final prox = enRes ? null : _proximidadPendiente(t);
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: _buildTurnoCompacto(
                                context,
                                t,
                                futuro: true,
                                proximidad: prox,
                                enResolucion: enRes,
                              ),
                            );
                          }),
                          if (_loadingMasPendientes)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 8),
                              child: Center(
                                child: SizedBox(
                                  width: 24,
                                  height: 24,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                ),
                              ),
                            ),
                          if (_hayMasPendientes && !_loadingMasPendientes)
                            TextButton(
                              onPressed: _cargarMasPendientes,
                              child: const Text('Cargar más'),
                            ),
                        ] else ...[
                          const SizedBox(height: 12),
                          if (_refrescandoTabActivo && _pasados.isEmpty)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 24),
                              child: Center(child: CircularProgressIndicator()),
                            ),
                          if (!_refrescandoTabActivo && _pasados.isEmpty)
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
                              child: Center(
                                child: SizedBox(
                                  width: 24,
                                  height: 24,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                ),
                              ),
                            ),
                          if (_hayMasPasados && !_loadingMasPasados)
                            TextButton(
                              onPressed: _cargarMasPasados,
                              child: const Text('Cargar más'),
                            ),
                        ],
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
    _ProximidadPendiente? proximidad,
    bool enResolucion = false,
  }) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    final estado = t['estado_label']?.toString() ?? t['estado']?.toString() ?? '';
    final idConsulta = futuro ? _idConsultaDesdeTurno(t) : null;

    Color bg;
    Color? borderColor;
    if (enResolucion) {
      final pair = _coloresTarjetaEnResolucion(context);
      bg = pair.bg;
      borderColor = pair.border;
    } else if (futuro && proximidad != null) {
      final pair = _coloresTarjetaProximo(context, proximidad);
      bg = pair.bg;
      borderColor = pair.border;
    } else {
      bg = futuro ? cs.surfaceContainerHighest : cs.surfaceContainerHigh;
    }

    return Card(
      elevation: 0,
      color: bg,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: borderColor != null
            ? BorderSide(color: borderColor, width: 1.2)
            : BorderSide.none,
      ),
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
            if (enResolucion && widget.onResolverTurno != null) ...[
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerLeft,
                child: FilledButton.icon(
                  icon: const Icon(Icons.build_circle_outlined, size: 18),
                  label: const Text('Resolver'),
                  onPressed: () => widget.onResolverTurno!(t),
                  style: FilledButton.styleFrom(
                    backgroundColor: cs.secondary,
                    foregroundColor: cs.onSecondary,
                    visualDensity: VisualDensity.compact,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                ),
              ),
            ],
            if (!enResolucion && idConsulta != null) ...[
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerLeft,
                child: OutlinedButton.icon(
                  icon: const Icon(Icons.edit_note, size: 18),
                  label: const Text('Cargar motivos de consulta'),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => ChatMotivosScreen(
                          consultaId: idConsulta,
                          authToken: widget.authToken,
                          userId: widget.userId,
                          userName: widget.userName,
                          titulo:
                              'Motivos · ${_fechaAmigable(t['fecha']?.toString())} · ${_horaSinSegundos(t['hora']?.toString())}',
                        ),
                      ),
                    );
                  },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: cs.primary,
                    side: BorderSide(color: cs.primary),
                    visualDensity: VisualDensity.compact,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                ),
              ),
            ],
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
                'No tienes turnos pendientes. Puedes pedir uno desde el chat.',
                style: tt.bodyMedium?.copyWith(color: cs.onSurfaceVariant),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
