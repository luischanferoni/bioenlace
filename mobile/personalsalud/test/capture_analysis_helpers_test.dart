import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/models/encounter_capture_analysis.dart';
import 'package:personalsalud/screens/timeline/capture/capture_analysis_helpers.dart';
import 'package:personalsalud/screens/timeline/capture/capture_server_snapshot.dart';

EncounterCaptureAnalysis _review({
  List<String>? defaultIds,
  List<Map<String, dynamic>> issues = const [],
}) {
  return EncounterCaptureAnalysis.fromCaptureReview({
    'texto_original': 'texto',
    if (defaultIds != null) 'default_staged_item_ids': defaultIds,
    'issues': issues,
  });
}

void main() {
  group('buildAnalysisPayload', () {
    test('sin snapshot devuelve la respuesta tal cual', () {
      final res = {'ok': true};
      expect(buildAnalysisPayload(res, null), same(res));
    });

    test('prefiere analysis y completa campos del capture', () {
      final res = {
        'capture': {
          'id': 1,
          'stage': 'READY_FOR_REVIEW',
          'analysis': {'capture_review': {}},
          'texto_procesado': 'procesado',
          'encounter_id': 9,
        },
      };
      final payload = buildAnalysisPayload(
        res,
        CaptureServerSnapshot.fromResponse(res),
      );
      expect(payload['texto_procesado'], 'procesado');
      expect(payload['encounter_id'], 9);
      expect(payload.containsKey('capture_review'), isTrue);
    });

    test('no pisa campos ya presentes en analysis', () {
      final res = {
        'capture': {
          'id': 1,
          'analysis': {'texto_procesado': 'analysis'},
          'texto_procesado': 'capture',
        },
      };
      final payload = buildAnalysisPayload(
        res,
        CaptureServerSnapshot.fromResponse(res),
      );
      expect(payload['texto_procesado'], 'analysis');
    });

    test('legacy: sube datos.datosExtraidos al primer nivel', () {
      final res = {
        'capture': {
          'id': 1,
          'analysis': {
            'datos': {
              'datosExtraidos': {'a': 1},
            },
          },
        },
      };
      final payload = buildAnalysisPayload(
        res,
        CaptureServerSnapshot.fromResponse(res),
      );
      expect(payload['datosExtraidos'], {'a': 1});
    });
  });

  group('defaultStagedItems', () {
    test('usa los ids por defecto del servidor', () {
      expect(defaultStagedItems(_review(defaultIds: ['a::1', 'b::2'])), {
        'a::1',
        'b::2',
      });
    });

    test('sin categorías ni ids devuelve vacío', () {
      expect(defaultStagedItems(_review()), isEmpty);
    });
  });

  group('stagedIdForIssue', () {
    test('recorta el detalle del issue', () {
      expect(stagedIdForIssue('dx::3:dosis'), 'dx::3');
    });

    test('sin patrón devuelve el mismo id', () {
      expect(stagedIdForIssue('general'), 'general');
    });
  });

  group('allStagedIssuesResolved', () {
    final review = _review(
      issues: [
        {'id': 'dx::1:dosis', 'field': 'dosis', 'allow_custom': true},
        {'id': 'dx::2:via', 'field': 'via'},
      ],
    );

    bool resolved(
      Set<String> staged,
      Map<String, dynamic> resolutions, [
      Map<String, String> custom = const {},
    ]) {
      return allStagedIssuesResolved(
        review: review,
        stagedItemIds: staged,
        resolutions: resolutions,
        customText: (id) => custom[id] ?? '',
      );
    }

    test('ignora issues de ítems no seleccionados', () {
      expect(resolved({}, {}), isTrue);
    });

    test('falla si un issue seleccionado no tiene resolución', () {
      expect(resolved({'dx::2'}, {}), isFalse);
    });

    test('acepta resolución elegida', () {
      expect(resolved({'dx::2'}, {'dx::2:via': 'oral'}), isTrue);
    });

    test('rechaza resolución vacía', () {
      expect(resolved({'dx::2'}, {'dx::2:via': '  '}), isFalse);
    });

    test('acepta texto propio solo si el issue lo permite', () {
      expect(resolved({'dx::1'}, {}, {'dx::1:dosis': '5 mg'}), isTrue);
      expect(resolved({'dx::2'}, {}, {'dx::2:via': 'oral'}), isFalse);
    });
  });
}
