import 'package:flutter_test/flutter_test.dart';
import 'package:shared/clinical/encounter_journey_navigation.dart';

void main() {
  test(
    'encounter preparation helpers read the configured pre-appointment phase',
    () {
      final turno = <String, dynamic>{
        'journey': <String, dynamic>{
          'phases': <String, dynamic>{
            'motivos_consulta': <String, dynamic>{
              'applies': true,
              'completed': false,
              'enabled': true,
            },
          },
        },
      };

      expect(encounterPreparationHasPending(turno), isTrue);
      expect(encounterPreparationHasAvailableAction(turno), isTrue);
      expect(encounterPreparationPendingPhases(turno), hasLength(1));
    },
  );

  test(
    'encounter follow-up helpers preserve the configured phase and touchpoint',
    () {
      final turno = <String, dynamic>{
        'journey': <String, dynamic>{
          'phases': <String, dynamic>{
            'post_consulta': <String, dynamic>{
              'applies': true,
              'completed': false,
              'followup': <String, dynamic>{
                'touchpoint_count': 1,
                'next_touchpoint_id': 23,
                'items': <Map<String, dynamic>>[
                  <String, dynamic>{
                    'id': 23,
                    'actionable': true,
                    'completed': false,
                  },
                ],
              },
            },
          },
        },
      };

      expect(encounterFollowupHasPending(turno), isTrue);
      expect(encounterFollowupHasAvailableAction(turno), isTrue);
      expect(
        touchpointIdFromEncounterFollowupPhase(
          journeyPhase(turno, kEncounterJourneyPhaseFollowup)!,
        ),
        23,
      );
    },
  );

  test(
    'encounter identifier reads the canonical field only',
    () {
      expect(
        encounterIdDesdeTurno(<String, dynamic>{
          'encounter_id': 34,
        }),
        34,
      );
      expect(encounterIdDesdeTurno(<String, dynamic>{'id_consulta': '12'}), isNull);
    },
  );
}
