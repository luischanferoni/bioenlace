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
      'tipo_label': 'Guardia',
      'intent': 'danger',
      'episodio_id': 45,
    });
    final internacion = ContextoEpisodioHistoria.fromJson(<String, dynamic>{
      'tipo': 'INTERNACION',
      'tipo_label': 'Internación',
      'intent': 'info',
      'episodio_id': 46,
    });

    expect(guardia.esGuardia, isTrue);
    expect(guardia.tipoLabel, 'Guardia');
    expect(guardia.intent, UiIntent.danger);
    expect(guardia.esInternacion, isFalse);
    expect(internacion.esGuardia, isFalse);
    expect(internacion.esInternacion, isTrue);
    expect(internacion.tipoLabel, 'Internación');
    expect(internacion.intent, UiIntent.info);
  });

  test('reads episode action presentation and API metadata', () {
    final episode = ContextoEpisodioHistoria.fromJson(<String, dynamic>{
      'tipo': 'guardia',
      'episodio_id': 45,
      'acciones': [
        <String, dynamic>{
          'id': 'editar_triage',
          'label': 'Registrar triage',
          'kind': 'native_triage',
          'intent': 'primary',
          'variant': 'outline',
          'api': <String, dynamic>{
            'route':
                '/clinical/emergency-guardia/registrar-triage-formulario?guardia_id=45',
            'method': 'GET',
          },
        },
      ],
    });

    expect(episode.acciones, hasLength(1));
    expect(episode.acciones.single.id, 'editar_triage');
    expect(episode.acciones.single.label, 'Registrar triage');
    expect(episode.acciones.single.kind, 'native_triage');
    expect(episode.acciones.single.intent, UiIntent.primary);
    expect(episode.acciones.single.variant, BioButtonVariant.outline);
    expect(
      episode.acciones.single.apiRoute,
      '/clinical/emergency-guardia/registrar-triage-formulario?guardia_id=45',
    );
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
      'series': [
        <String, dynamic>{
          'metric': 'custom_metric',
          'label': 'Frecuencia respiratoria',
          'unit': 'rpm',
          'intent': 'success',
          'points': [
            <String, dynamic>{'at': '21/10/2026 19:00', 'value': 18},
          ],
        },
      ],
      'ultimos': {
        'custom_metric': <String, dynamic>{
          'value': 18,
          'label': 'Frecuencia respiratoria',
          'unit': 'rpm',
          'intent': 'info',
          'at': '21/10/2026 19:00',
        },
      },
    });

    expect(vitals.ultimos, hasLength(1));
    expect(vitals.ultimos['custom_metric']?.label, 'Frecuencia respiratoria');
    expect(vitals.ultimos['custom_metric']?.value, 18);
    expect(vitals.ultimos['custom_metric']?.unit, 'rpm');
    expect(vitals.ultimos['custom_metric']?.intent, UiIntent.info);
    expect(vitals.series.single.label, 'Frecuencia respiratoria');
    expect(vitals.series.single.intent, UiIntent.success);
  });

  test('reads generic clinical documentation field metadata', () {
    final documentation = DocumentacionMedico.fromJson(<String, dynamic>{
      'encounter_id': 34,
      'tiene_datos': true,
      'secciones': [
        <String, dynamic>{
          'key': 'diagnoses',
          'label': 'Diagnósticos',
          'titulo': 'Diagnósticos',
          'fields': [
            <String, dynamic>{
              'key': 'item_1',
              'label': 'Detalle',
              'value': 'Hipertensión',
              'unit': null,
              'format': 'text',
            },
          ],
        },
      ],
    });

    expect(documentation.secciones, hasLength(1));
    expect(documentation.secciones.single.key, 'diagnoses');
    expect(documentation.secciones.single.titulo, 'Diagnósticos');
    expect(documentation.secciones.single.fields.single.value, 'Hipertensión');
    expect(documentation.secciones.single.fields.single.format, 'text');
    expect(documentation.secciones.single.items, isEmpty);
  });

  test('keeps reading legacy clinical documentation items', () {
    final documentation = DocumentacionMedico.fromJson(<String, dynamic>{
      'encounter_id': 34,
      'tiene_datos': true,
      'secciones': [
        <String, dynamic>{
          'titulo': 'Evolución',
          'items': ['Control clínico'],
        },
      ],
    });

    expect(documentation.secciones.single.titulo, 'Evolución');
    expect(documentation.secciones.single.items, ['Control clínico']);
    expect(documentation.secciones.single.fields, isEmpty);
  });
}
