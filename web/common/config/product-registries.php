<?php

/**
 * Registro de handlers de dominio para motores genéricos (Bioenlace / salud).
 *
 * Fuente única de cableado producto → motor. Para otro rubro: reemplazar este archivo en params-local.
 *
 * @see \common\components\Platform\Core\Product\ProductRegistryConfig
 */

use common\components\Platform\Assistant\Catalog\DataAccessUiActionCatalog;
use common\components\Domain\Clinical\Assistant\ClinicalConversationalChannelProvider;
use common\components\Domain\Clinical\Assistant\ClinicalUiActionCatalog;
use common\components\Domain\Clinical\CareCohort\Assistant\CarePackUiActionCatalog;
use common\components\Domain\Clinical\Home\InpatientHomePanelSliceResolver;
use common\components\Domain\Clinical\Home\Sections\EmergencyBoardSectionProvider;
use common\components\Domain\Clinical\Home\Sections\EmergencyIndicatorsSectionProvider;
use common\components\Domain\Clinical\Home\Sections\InpatientsSectionProvider;
use common\components\Domain\Clinical\Home\Sections\PatientCarePlansActiveSectionProvider;
use common\components\Domain\Clinical\Home\Sections\StaffGuardiaKpiSectionProvider;
use common\components\Domain\Clinical\Home\Sections\StaffInternacionKpiSectionProvider;
use common\components\Domain\Clinical\Inpatient\Service\Authorization\ClinicalInternacionStaffAccessPolicy;
use common\components\Domain\Clinical\Service\Authorization\ClinicalEncounterAccessPolicy;
use common\components\Platform\Ui\Home\Service\Sections\StaffSessionContextSectionProvider;
use common\components\Platform\Core\DataAccess\DataAccessEditFlowDraftHydrator;
use common\components\Platform\Core\DataAccess\DataAccessFlowDraftHydrator;
use common\components\Platform\Core\DataAccess\Edit\Handler\PersonIdentidadBasicaEditMutationHandler;
use common\components\Domain\Organization\DataAccess\Filter\ServicioRolEfectorIdsFilterResolver;
use common\components\Domain\Organization\DataAccess\Filter\ServicioRolFromMentionFilterResolver;
use common\components\Domain\Organization\DataAccess\Scope\EfectorSesionScopeChecker;
use common\components\Domain\Organization\DataAccess\Scope\EfectorSesionViaPesScopeChecker;
use common\components\Domain\Organization\Presentation\ProfesionalesConteoInfoPresentation;
use common\components\Domain\Organization\Presentation\ProfesionalesListadoRowsPresentation;
use common\components\Domain\Organization\Service\Authorization\OrganizationEfectorSesionPolicy;
use common\components\Domain\Organization\Service\Authorization\OrganizationPesEfectorPolicy;
use common\components\Domain\Organization\Service\Authorization\OrganizationPesOwnPolicy;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\OrganizationIntentSubjectResolvers;
use common\components\Domain\Person\Assistant\PacienteRecursoProvincialFlowDraftHydrator;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaFlowDraftHydrator;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioCrearFlowDraftHydrator;
use common\components\Domain\Person\DataAccess\Filter\SexoBiologicoFilterResolver;
use common\components\Domain\Person\DataAccess\Scope\PermitirParaSiMismoScopeChecker;
use common\components\Domain\Person\Representation\Assistant\PersonRepresentationUiActionCatalog;
use common\components\Domain\Scheduling\Home\Sections\AppointmentsDaySectionProvider;
use common\components\Domain\Scheduling\Home\Sections\PatientConsultaAsyncSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\PatientUpcomingAppointmentsSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffConsultaAsyncBandejaSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffEfectorModalidadKpiSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffAgendaKpiSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\StaffSurgeryKpiSectionProvider;
use common\components\Domain\Scheduling\Home\Sections\SurgeriesDaySectionProvider;
use common\components\Domain\Scheduling\Service\Authorization\TurnoCreateSubjectPolicy;
use common\components\Domain\Scheduling\Service\Authorization\TurnoStaffEfectorBelongsPolicy;
use common\components\Domain\Scheduling\Service\Authorization\TurnoSubjectOrRepresentativePolicy;
use common\models\Condiciones_laborales;
use common\components\Domain\Organization\Assistant\OrganizationHintCandidateProvider;
use common\components\Domain\Organization\Assistant\OrganizationUiSelectOptionSourceProvider;
use common\components\Domain\Person\Assistant\PersonHintCandidateProvider;
use common\components\Domain\Scheduling\Assistant\SchedulingHintCandidateProvider;
use common\components\Domain\Scheduling\Assistant\SchedulingUiSelectOptionSourceProvider;
use common\components\Domain\Scheduling\Assistant\SchedulingUiScreenParamsExpander;
use common\components\Domain\Scheduling\Service\ReservaTurnoTriageFlowDraftHydrator;
use common\components\Platform\Ui\Home\Service\Sections\ActionCardsSectionProvider;

return [
    'flowDraftHydrators' => [
        'organization.pes_crear_alta' => [ProfesionalEfectorServicioCrearFlowDraftHydrator::class, 'hydrateWithOptions'],
        'organization.pes_from_servicio' => [ProfesionalEfectorServicioAgendaFlowDraftHydrator::class, 'hydrateWithOptions'],
        'data_access.metric_flow' => [DataAccessFlowDraftHydrator::class, 'hydrateWithOptions'],
        'data_access.edit_flow' => [DataAccessEditFlowDraftHydrator::class, 'hydrateWithOptions'],
        'scheduling.reserva_triage' => [ReservaTurnoTriageFlowDraftHydrator::class, 'hydrateWithOptions'],
        'person.paciente_recurso_provincial' => [PacienteRecursoProvincialFlowDraftHydrator::class, 'hydrateWithOptions'],
    ],

    'intentSubjectResolvers' => [
        'organization.pes_own_in_efector' => [OrganizationIntentSubjectResolvers::class, 'hydratePesOwnInEfector'],
        'organization.pes_staff_in_efector' => [OrganizationIntentSubjectResolvers::class, 'hydratePesStaffInEfector'],
    ],

    'domainOperationPolicies' => [
        'turno.subject_or_representative' => TurnoSubjectOrRepresentativePolicy::class,
        'turno.staff_efector_belongs' => TurnoStaffEfectorBelongsPolicy::class,
        'turno.create_subject_or_representative' => TurnoCreateSubjectPolicy::class,
        'organization.efector_sesion' => OrganizationEfectorSesionPolicy::class,
        'organization.pes_efector' => OrganizationPesEfectorPolicy::class,
        'organization.pes_own' => OrganizationPesOwnPolicy::class,
        'clinical.encounter_participant' => ClinicalEncounterAccessPolicy::class,
        'clinical.internacion_staff_access' => ClinicalInternacionStaffAccessPolicy::class,
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
            'organization.profesionales_conteo_info' => ProfesionalesConteoInfoPresentation::class,
        ],
        'list' => [
            'organization.profesionales_listado_rows' => ProfesionalesListadoRowsPresentation::class,
        ],
    ],

    'dataAccessEditMutationHandlers' => [
        PersonIdentidadBasicaEditMutationHandler::class,
    ],

    'homePanelStaffPanelSliceResolvers' => [
        InpatientHomePanelSliceResolver::class,
    ],

    'uiActionCatalogProviders' => [
        ClinicalUiActionCatalog::class,
        CarePackUiActionCatalog::class,
        PersonRepresentationUiActionCatalog::class,
        DataAccessUiActionCatalog::class,
    ],

    'conversationalChannelProviders' => [
        ClinicalConversationalChannelProvider::class,
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

    'homePanelSectionProviders' => [
        'emergency_board' => EmergencyBoardSectionProvider::class,
        'emergency_indicators' => EmergencyIndicatorsSectionProvider::class,
        'appointments_day' => AppointmentsDaySectionProvider::class,
        'async_consultations_queue' => StaffConsultaAsyncBandejaSectionProvider::class,
        'inpatients' => InpatientsSectionProvider::class,
        'surgeries_day' => SurgeriesDaySectionProvider::class,
        'action_cards' => ActionCardsSectionProvider::class,
        'patient_upcoming_appointments' => PatientUpcomingAppointmentsSectionProvider::class,
        'patient_async_consultations' => PatientConsultaAsyncSectionProvider::class,
        'patient_care_plans_active' => PatientCarePlansActiveSectionProvider::class,
        'staff_session_context' => StaffSessionContextSectionProvider::class,
        'staff_agenda_kpis' => StaffAgendaKpiSectionProvider::class,
        'staff_efector_modalidad_kpis' => StaffEfectorModalidadKpiSectionProvider::class,
        'staff_guardia_kpis' => StaffGuardiaKpiSectionProvider::class,
        'staff_internacion_kpis' => StaffInternacionKpiSectionProvider::class,
        'staff_surgery_kpis' => StaffSurgeryKpiSectionProvider::class,
    ],
];
