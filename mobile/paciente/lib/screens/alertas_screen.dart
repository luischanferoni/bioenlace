import 'package:flutter/material.dart';

import '../services/notificaciones_service.dart';
import '../services/push_notification_service.dart';
import '../theme/paciente_theme_extensions.dart';

/// Bandeja de alertas in-app del paciente.
class AlertasScreen extends StatefulWidget {
  final String? authToken;
  final void Function(Map<String, dynamic> turnoStub)? onAbrirResolver;

  const AlertasScreen({
    Key? key,
    this.authToken,
    this.onAbrirResolver,
  }) : super(key: key);

  @override
  State<AlertasScreen> createState() => _AlertasScreenState();
}

class _AlertasScreenState extends State<AlertasScreen> {
  late NotificacionesService _svc;
  final List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  int _noLeidas = 0;

  @override
  void initState() {
    super.initState();
    _svc = NotificacionesService(authToken: widget.authToken);
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final r = await _svc.listar(limit: 50);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (r['success'] == true) {
        _items
          ..clear()
          ..addAll(
            (r['items'] as List<dynamic>? ?? [])
                .map((e) => Map<String, dynamic>.from(e as Map)),
          );
        _noLeidas = r['no_leidas'] as int? ?? 0;
      } else {
        _error = r['message'] as String?;
      }
    });
  }

  Future<void> _marcarLeida(Map<String, dynamic> item) async {
    final id = item['id'];
    if (id is! int && id is! num) return;
    await _svc.marcarLeida(id: id is int ? id : id.toInt());
    await _cargar();
  }

  void _onTapItem(Map<String, dynamic> item) {
    _marcarLeida(item);
    final data = item['data'];
    if (data is! Map) return;
    final stub = PushNotificationService.turnoStubDesdePush(
      Map<String, dynamic>.from(data),
    );
    if (stub != null && widget.onAbrirResolver != null) {
      widget.onAbrirResolver!(stub);
      Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;

    return Scaffold(
      appBar: AppBar(
        title: Text('Alertas${_noLeidas > 0 ? ' ($_noLeidas)' : ''}'),
        actions: [
          if (_noLeidas > 0)
            TextButton(
              onPressed: () async {
                await _svc.marcarLeida();
                await _cargar();
              },
              child: const Text('Marcar todas leídas'),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : RefreshIndicator(
                  onRefresh: _cargar,
                  child: _items.isEmpty
                      ? ListView(
                          children: [
                            SizedBox(
                              height: MediaQuery.of(context).size.height * 0.3,
                            ),
                            Center(
                              child: Text(
                                'No tenés alertas.',
                                style: tt.bodyMedium?.copyWith(color: cs.onSurfaceVariant),
                              ),
                            ),
                          ],
                        )
                      : ListView.builder(
                          itemCount: _items.length,
                          itemBuilder: (context, i) {
                            final item = _items[i];
                            final leida = item['leida'] == true;
                            return ListTile(
                              tileColor: leida ? null : cs.secondaryContainer.withValues(alpha: 0.35),
                              title: Text(
                                item['titulo']?.toString() ?? '',
                                style: TextStyle(
                                  fontWeight: leida ? FontWeight.normal : FontWeight.w600,
                                ),
                              ),
                              subtitle: Text(item['cuerpo']?.toString() ?? ''),
                              onTap: () => _onTapItem(item),
                            );
                          },
                        ),
                ),
    );
  }
}
