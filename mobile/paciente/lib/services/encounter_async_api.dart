import 'package:shared/shared.dart';

/// Patient-facing actions for asynchronous encounter requests.
class EncounterAsyncApi extends EncounterAsyncClient {
  EncounterAsyncApi({super.authToken, super.client})
    : super(appClient: 'bioenlace-paciente');
}
