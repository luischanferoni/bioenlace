<?php

/**
 * Registro de handlers de dominio para motores genéricos (Bioenlace / salud).
 *
 * Fuente única de cableado producto → motor. Para otro rubro: reemplazar este archivo en params-local.
 *
 * @see \common\components\Platform\Core\Product\ProductRegistryConfig
 */

use common\components\Platform\Assistant\Catalog\DataAccessUiActionCatalog;
use common\components\Domain\Clinical\CareCohort\Domain\Catalog\CarePackUiActionCatalog;
use common\components\Domain\Clinical\CarePlan\Domain\Catalog\CarePlanUiActionCatalog;
use common\components\Domain\Clinical\Emergency\Domain\Catalog\EmergencyUiActionCatalog;
use common\components\Domain\Clinical\Encounter\Application\Service\ClinicalConversationalChannelService;
use common\components\Domain\Clinical\Encounter\Domain\Catalog\EncounterUiActionCatalog;
use common\components\Domain\Clinical\Home\Domain\Catalog\HomeUiActionCatalog;
use common\components\Domain\Clinical\Home\Application\Service\InpatientHomePanelSliceResolver;
use common\components\Domain\Clinical\Home\Sections\EmergencyBoardSectionProvider;
use common\components\Domain\Clinical\Home\Sections\EmergencyIndicatorsSectionProvider;
use common\components\Domain\Clinical\Home\Sections\StaffHorarioActivoSectionProvider;
use common\components\Domain\Clinical\Home\Sections\InpatientsSectionProvider;
use common\components\Domain\Clinical\Home\Sections\PatientCarePlansActiveSectionProvider;
use common\components\Domain\Clinical\Home\Sections\PatientConditionsActiveSectionProvider;
use common\components\Domain\Clinical\Home\Sections\StaffEmergencyKpiSectionProvider;
use common\components\Domain\Clinical\Home\Sections\StaffInpatientKpiSectionProvider;
use common\components\Domain\Clinical\Inpatient\Application\Authorization\ClinicalInpatientStaffAccessPolicy;
use common\components\Domain\Clinical\Inpatient\Domain\Catalog\InpatientUiActionCatalog;
use common\components\Domain\Clinical\Laboratory\Domain\Catalog\LaboratoryUiActionCatalog;
use common\components\Domain\Clinical\Encounter\Application\Authorization\ClinicalEncounterAccessPolicy;
use common\components\Domain\Clinical\Prescription\Domain\Catalog\PrescriptionUiActionCatalog;
use common\components\Platform\Ui\Home\Service\Sections\StaffSessionContextSectionProvider;
use common\components\Platform\Core\DataAccess\DataAccessEditFlowDraftHydrator;
use common\components\Platform\Core\DataAccess\DataAccessFlowDraftHydrator;
use common\components\Platform\Core\DataAccess\Edit\Handler\PersonIdentidadBasicaEditMutationHandler;
use common\components\Domain\Organization\DataAccess\Filter\ServicioRolEfectorIdsFilterResolver;
use common\components\Domain\Organization\DataAccess\Filter\ServicioRolFromMentionFilterResolver;
use common\components\Domain\Organization\DataAccess\Scope\EfectorSesionScopeChecker;
use common\components\Domain\Organization\DataAccess\Scope\EfectorSesionViaPesScopeChecker;
use common\components\Domain\Organization\Pes\Application\Presentation\ProfesionalesConteoInfoPresenter;
use common\components\Domain\Organization\Pes\Application\Presentation\ProfesionalesDistribucionInfoPresenter;
use common\components\Domain\Organization\Pes\Application\Presentation\ProfesionalesListadoRowsPresenter;
use common\components\Domain\Organization\Efector\Application\Authorization\OrganizationEfectorSesionAccess;
use common\components\Domain\Organization\Pes\Application\Authorization\OrganizationPesEfectorAccess;
use common\components\Domain\Organization\Pes\Application\Authorization\OrganizationPesOwnAccess;
use common\components\Domain\Organization\Pes\Application\Service\OrganizationIntentSubjectResolver;
use common\components\Domain\Person\Assistant\PacienteRecursoProvincialFlowDraftHydrator;
use common\components\Domain\Organization\Assistant\ProfesionalEfectorServicioAgendaFlowDraftHydrator;
use common\components\Domain\Organization\Assistant\ProfesionalEfectorServicioCrearFlowDraftHydrator;
use common\components\Domain\Person\DataAccess\Filter\SexoBiologicoFilterResolver;
use common\components\Domain\Person\DataAccess\Scope\PermitirParaSiMismoScopeChecker;
use common\components\Domain\Person\Representation\Domain\Catalog\PersonRepresentationUiActionCatalog;
use common\components\Domain\Scheduling\Home\Sections\AppointmentsDaySectionProvider;
use common\components\Domain\Scheduling\Home\Sections\PatientConsultaAsyncSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\PatientUpcomingAppointmentsSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffConsultaAsyncBandejaSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffEfectorModalidadKpiSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffAgendaKpiSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffSurgeryKpiSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\SurgeriesDaySectionProvider;
use common\components\Domain\Scheduling\Agenda\Application\Authorization\TurnoCreateSubjectAccess;
use common\components\Domain\Scheduling\Agenda\Application\Authorization\TurnoStaffEfectorBelongsAccess;
use common\components\Domain\Scheduling\Agenda\Application\Authorization\TurnoSubjectOrRepresentativeAccess;
use common\models\Organization\Condiciones_laborales;
use common\components\Domain\Organization\Assistant\OrganizationHintCandidateProvider;
use common\components\Domain\Organization\Assistant\OrganizationUiSelectOptionSourceProvider;
use common\components\Domain\Person\Assistant\PersonHintCandidateProvider;
use common\components\Domain\Scheduling\Assistant\SchedulingHintCandidateProvider;
use common\components\Domain\Scheduling\Assistant\SchedulingUiSelectOptionSourceProvider;
use common\components\Domain\Scheduling\Assistant\SchedulingUiScreenParamsExpander;
use common\components\Domain\Scheduling\Assistant\ConsultasSeguimientoFlowDraftHydrator;
use common\components\Domain\Scheduling\Assistant\ReservaTurnoTriageFlowDraftHydrator;
use common\components\Domain\Scheduling\Assistant\TurnosVerMisTurnosFlowDraftHydrator;
use common\components\Domain\Scheduling\Assistant\SolicitarAtencionFlowDraftHydrator;
use common\components\Domain\Scheduling\Assistant\Context\AppointmentCurrentAspectLoader;
use common\components\Domain\Scheduling\Assistant\Context\AppointmentHistorySubjectAtSiteAspectLoader;
use common\components\Domain\Scheduling\Assistant\Context\AppointmentSchedulingSetupAspectLoader;
use common\components\Domain\Scheduling\Assistant\Context\SiteAppointmentPoliciesAspectLoader;
use common\components\Domain\Scheduling\BehaviorProfile\Application\Service\TurnoConfirmationPushReceiptService;
use common\components\Domain\Scheduling\BehaviorProfile\Application\Service\TurnoAdvancePushReceiptService;
use common\components\Platform\Ui\Home\Service\Sections\ActionCardsSectionProvider;

return [
    'flowDraftHydrators' => [
        'organization.pes_crear_alta' => [ProfesionalEfectorServicioCrearFlowDraftHydrator::class, 'hydrateWithOptions'],
        'organization.pes_from_servicio' => [ProfesionalEfectorServicioAgendaFlowDraftHydrator::class, 'hydrateWithOptions'],
        'data_access.metric_flow' => [DataAccessFlowDraftHydrator::class, 'hydrateWithOptions'],
        'data_access.edit_flow' => [DataAccessEditFlowDraftHydrator::class, 'hydrateWithOptions'],
        'scheduling.reserva_triage' => [ReservaTurnoTriageFlowDraftHydrator::class, 'hydrateWithOptions'],
        'scheduling.solicitar_atencion' => [SolicitarAtencionFlowDraftHydrator::class, 'hydrateWithOptions'],
        'scheduling.consultas_seguimiento_intake' => [ConsultasSeguimientoFlowDraftHydrator::class, 'hydrateWithOptions'],
        'scheduling.turnos_listar_como_paciente' => [TurnosVerMisTurnosFlowDraftHydrator::class, 'hydrateWithOptions'],
        'person.paciente_recurso_provincial' => [PacienteRecursoProvincialFlowDraftHydrator::class, 'hydrateWithOptions'],
    ],

    'intentSubjectResolvers' => [
        'organization.pes_own_in_efector' => [OrganizationIntentSubjectResolver::class, 'hydratePesOwnInEfector'],
        'organization.pes_staff_in_efector' => [OrganizationIntentSubjectResolver::class, 'hydratePesStaffInEfector'],
    ],

    'domainOperationPolicies' => [
        'turno.subject_or_representative' => TurnoSubjectOrRepresentativeAccess::class,
        'turno.staff_efector_belongs' => TurnoStaffEfectorBelongsAccess::class,
        'turno.create_subject_or_representative' => TurnoCreateSubjectAccess::class,
        'organization.efector_sesion' => OrganizationEfectorSesionAccess::class,
        'organization.pes_efector' => OrganizationPesEfectorAccess::class,
        'organization.pes_own' => OrganizationPesOwnAccess::class,
        'clinical.encounter_participant' => ClinicalEncounterAccessPolicy::class,
        'clinical.internacion_staff_access' => ClinicalInpatientStaffAccessPolicy::class,
    ],

    'dataAccessScopeCheckers' => [
        'efector_sesion' => EfectorSesionScopeChecker::class,
        'efector_sesion_via_pes' => EfectorSesionViaPesScopeChecker::class,
        'permitir_para_si_mismo' => PermitirParaSiMismoScopeChecker::class,
    ],

    'dataAccessFilterResolvers' => [
        'servicio_rol_efector_ids' => ServicioRolEfectorIdsFilterResolver::class,
        'servicio_rol_from_mention' => ServicioRolFromMentionFilterResolver::class,
        'sexo_biologico' => SexoBiologicoFilterResolver::class,
    ],

    'metricPresentationHandlers' => [
        'info' => [
            'organization.profesionales_conteo_info' => ProfesionalesConteoInfoPresenter::class,
            'organization.profesionales_distribucion_info' => ProfesionalesDistribucionInfoPresenter::class,
        ],
        'list' => [
            'organization.profesionales_listado_rows' => ProfesionalesListadoRowsPresenter::class,
        ],
    ],

    'dataAccessEditMutationHandlers' => [
        PersonIdentidadBasicaEditMutationHandler::class,
    ],

    'homePanelStaffPanelSliceResolvers' => [
        InpatientHomePanelSliceResolver::class,
    ],

    'uiActionCatalogProviders' => [
        EncounterUiActionCatalog::class,
        CarePlanUiActionCatalog::class,
        LaboratoryUiActionCatalog::class,
        PrescriptionUiActionCatalog::class,
        EmergencyUiActionCatalog::class,
        InpatientUiActionCatalog::class,
        HomeUiActionCatalog::class,
        CarePackUiActionCatalog::class,
        PersonRepresentationUiActionCatalog::class,
        DataAccessUiActionCatalog::class,
    ],

    'conversationalChannelProviders' => [
        ClinicalConversationalChannelService::class,
    ],

    'assistantContextAspectLoaders' => [
        AppointmentCurrentAspectLoader::class,
        SiteAppointmentPoliciesAspectLoader::class,
        AppointmentSchedulingSetupAspectLoader::class,
        AppointmentHistorySubjectAtSiteAspectLoader::class,
    ],

    'hintCandidateProviders' => [
        SchedulingHintCandidateProvider::class,
        OrganizationHintCandidateProvider::class,
        PersonHintCandidateProvider::class,
    ],

    'uiScreenParamsExpanders' => [
        SchedulingUiScreenParamsExpander::class,
    ],

    'uiSelectOptionSourceProviders' => [
        OrganizationUiSelectOptionSourceProvider::class,
        SchedulingUiSelectOptionSourceProvider::class,
    ],

    'uiCatalogOptionDefinitions' => [
        'condiciones_laborales' => [
            'class' => Condiciones_laborales::class,
            'value' => 'id_condicion_laboral',
            'label' => 'nombre',
            'orderBy' => ['nombre' => SORT_ASC],
        ],
    ],

    'pushNotificationReceiptProjectors' => [
        TurnoConfirmationPushReceiptService::HANDLER_ID => TurnoConfirmationPushReceiptService::class,
        TurnoAdvancePushReceiptService::HANDLER_ID => TurnoAdvancePushReceiptService::class,
    ],

    'homePanelSectionProviders' => [
        'emergency_board' => EmergencyBoardSectionProvider::class,
        'emergency_indicators' => EmergencyIndicatorsSectionProvider::class,
        'staff_horario_activo' => StaffHorarioActivoSectionProvider::class,
        'appointments_day' => AppointmentsDaySectionProvider::class,
        'async_consultations_queue' => StaffConsultaAsyncBandejaSectionProvider::class,
        'inpatients' => InpatientsSectionProvider::class,
        'surgeries_day' => SurgeriesDaySectionProvider::class,
        'action_cards' => ActionCardsSectionProvider::class,
        'patient_upcoming_appointments' => PatientUpcomingAppointmentsSectionProvider::class,
        'patient_async_consultations' => PatientConsultaAsyncSectionProvider::class,
        'patient_care_plans_active' => PatientCarePlansActiveSectionProvider::class,
        'patient_conditions_active' => PatientConditionsActiveSectionProvider::class,
        'staff_session_context' => StaffSessionContextSectionProvider::class,
        'staff_agenda_kpis' => StaffAgendaKpiSectionProvider::class,
        'staff_efector_modalidad_kpis' => StaffEfectorModalidadKpiSectionProvider::class,
        'staff_guardia_kpis' => StaffEmergencyKpiSectionProvider::class,
        'staff_internacion_kpis' => StaffInpatientKpiSectionProvider::class,
        'staff_surgery_kpis' => StaffSurgeryKpiSectionProvider::class,
    ],
];
