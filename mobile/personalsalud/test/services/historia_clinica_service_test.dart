import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/services/historia_clinica_service.dart';
import 'package:shared/shared.dart';

void main() {
  test('reads encounter reason summary from the current API contract', () {
    final response = HistoriaClinicaResponse.fromJson(<String, dynamic>{
      'persona': <String, dynamic>{'id': 12, 'nombre_completo': 'Paciente'},
      'informacion_medica': <String, dynamic>{
        'encounter_reason_summary': 'Control de seguimiento',
      },
      'patient_encounter_reasons': <String, dynamic>{
        'encounter_id': 34,
        'messages': [],
      },
    });

    expect(response.patientEncounterReasons.encounterId, 34);
    expect(
      response.informacionMedica.encounterReasonSummary,
      'Control de seguimiento',
    );
  });

  test('recognizes encounter episode parent types case-insensitively', () {
    final guardia = ContextoEpisodioHistoria.fromJson(<String, dynamic>{
      'tipo': 'guardia',
      'episodio_id': 45,
    });
    final internacion = ContextoEpisodioHistoria.fromJson(<String, dynamic>{
      'tipo': 'INTERNACION',
      'episodio_id': 46,
    });

    expect(guardia.esGuardia, isTrue);
    expect(guardia.esInternacion, isFalse);
    expect(internacion.esGuardia, isFalse);
    expect(internacion.esInternacion, isTrue);
  });

  test('reads episode action presentation and API metadata', () {
    final episode = ContextoEpisodioHistoria.fromJson(<String, dynamic>{
      'tipo': 'guardia',
      'episodio_id': 45,
      'acciones': [
        <String, dynamic>{
          'id': 'abrir_formulario',
          'label': 'Abrir formulario',
          'kind': 'ui_json',
          'api': <String, dynamic>{
            'route': '/clinical/formulario',
            'method': 'GET',
          },
        },
      ],
    });

    expect(episode.acciones, hasLength(1));
    expect(episode.acciones.single.id, 'abrir_formulario');
    expect(episode.acciones.single.label, 'Abrir formulario');
    expect(episode.acciones.single.kind, 'ui_json');
    expect(episode.acciones.single.apiRoute, '/clinical/formulario');
    expect(episode.acciones.single.apiMethod, 'GET');
  });

  test('reads episode timeline item labels and filter metadata', () {
    final feed = TimelineEpisodioFeed.fromJson(<String, dynamic>{
      'parent_type': 'guardia',
      'episodio_id': 45,
      'filters': [
        <String, dynamic>{'id': 'all', 'label': 'Todos', 'types': null},
        <String, dynamic>{
          'id': 'clinical',
          'label': 'Clínico',
          'types': ['evolucion_medica', 'triage'],
        },
      ],
      'items': [
        <String, dynamic>{
          'id': 'triage:1',
          'type': 'triage',
          'type_label': 'Triage',
          'intent': 'danger',
          'summary': 'Triage Naranja',
        },
      ],
    });

    expect(feed.filters, hasLength(2));
    expect(feed.filters.first.types, isNull);
    expect(feed.filters.last.types, ['evolucion_medica', 'triage']);
    expect(feed.items.single.typeLabel, 'Triage');
    expect(feed.items.single.intent, UiIntent.danger);
  });

  test('reads latest episode vitals as metadata-driven metrics', () {
    final vitals = SignosVitalesEpisodio.fromJson(<String, dynamic>{
      'parent_type': 'guardia',
      'episodio_id': 45,
      'total_points': 1,
      'ultimos': {
        'custom_metric': <String, dynamic>{
          'value': 18,
          'label': 'Frecuencia respiratoria',
          'unit': 'rpm',
          'at': '21/10/2026 19:00',
        },
      },
    });

    expect(vitals.ultimos, hasLength(1));
    expect(vitals.ultimos['custom_metric']?.label, 'Frecuencia respiratoria');
    expect(vitals.ultimos['custom_metric']?.value, 18);
    expect(vitals.ultimos['custom_metric']?.unit, 'rpm');
  });
}
