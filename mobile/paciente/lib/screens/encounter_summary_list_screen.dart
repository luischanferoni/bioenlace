import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import 'encounter_summary_detail_screen.dart';

/// Listado de atenciones publicadas del paciente.
class EncounterSummaryListScreen extends StatefulWidget {
  final String? authToken;

  const EncounterSummaryListScreen({super.key, this.authToken});

  @override
  State<EncounterSummaryListScreen> createState() => _EncounterSummaryListScreenState();
}

class _EncounterSummaryListScreenState extends State<EncounterSummaryListScreen> {
  late EncounterPatientSummaryApi _api;
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];

  @override
  void initState() {
    super.initState();
    _api = EncounterPatientSummaryApi(authToken: widget.authToken);
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final r = await _api.list(limit: 50);
      if (r['success'] == true && r['data'] is Map) {
        final data = Map<String, dynamic>.from(r['data'] as Map);
        final raw = data['items'];
        _items = raw is List
            ? raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
            : [];
      } else {
        _error = r['message']?.toString();
      }
    } catch (e) {
      _error = e.toString();
    }
    if (!mounted) return;
    setState(() => _loading = false);
  }

  void _openDetail(int encounterId) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => EncounterSummaryDetailScreen(
          encounterId: encounterId,
          authToken: widget.authToken,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Mis atenciones'),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: BioSpacing.pageAll,
                    child: BioAlert.danger(message: _error!),
                  ),
                )
              : _items.isEmpty
                  ? Center(
                      child: Padding(
                        padding: BioSpacing.pageAll,
                        child: Text(
                          'Todavía no hay atenciones publicadas.',
                          style: BioTypography.body.copyWith(color: tokens.textMuted),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: BioSpacing.pageAll,
                        itemCount: _items.length,
                        separatorBuilder: (_, __) => BioSpacing.gapH(BioSpacing.sm),
                        itemBuilder: (context, index) {
                          final item = _items[index];
                          final id = int.tryParse(item['encounterId']?.toString() ?? '') ?? 0;
                          final title = item['efectorNombre']?.toString() ??
                              'Atención';
                          final subtitle = [
                            item['profesionalDisplay']?.toString(),
                            item['publishedAt']?.toString(),
                          ].where((s) => s != null && s.isNotEmpty).join(' · ');

                          return BioCard(
                            child: ListTile(
                              title: Text(title, style: BioTypography.title),
                              subtitle: subtitle.isNotEmpty
                                  ? Text(subtitle, style: BioTypography.bodySm)
                                  : null,
                              trailing: item['teaser']?.toString().isNotEmpty == true
                                  ? Icon(Icons.chevron_right, color: tokens.textMuted)
                                  : null,
                              onTap: id > 0 ? () => _openDetail(id) : null,
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
