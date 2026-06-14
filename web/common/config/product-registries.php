<?php

/**
 * Registro de handlers de dominio para motores genéricos (Bioenlace / salud).
 *
 * Fuente única de cableado producto → motor. Para otro rubro: reemplazar este archivo en params-local.
 *
 * @see \common\components\Core\Product\ProductRegistryConfig
 */

use common\components\Assistant\Catalog\DataAccessUiActionCatalog;
use common\components\Clinical\Assistant\ClinicalUiActionCatalog;
use common\components\Clinical\CareCohort\Assistant\CarePackUiActionCatalog;
use common\components\Clinical\Home\InpatientHomePanelSliceResolver;
use common\components\Clinical\Home\Sections\EmergencyBoardSectionProvider;
use common\components\Clinical\Home\Sections\EmergencyIndicatorsSectionProvider;
use common\components\Clinical\Home\Sections\InpatientsSectionProvider;
use common\components\Clinical\Home\Sections\PatientCarePlansActiveSectionProvider;
use common\components\Clinical\Inpatient\Service\Authorization\ClinicalInternacionStaffAccessPolicy;
use common\components\Clinical\Service\Authorization\ClinicalEncounterAccessPolicy;
use common\components\Core\DataAccess\DataAccessEditFlowDraftHydrator;
use common\components\Core\DataAccess\DataAccessFlowDraftHydrator;
use common\components\Core\DataAccess\Edit\Handler\PersonIdentidadBasicaEditMutationHandler;
use common\components\Organization\DataAccess\Filter\ServicioRolEfectorIdsFilterResolver;
use common\components\Organization\DataAccess\Filter\ServicioRolFromMentionFilterResolver;
use common\components\Organization\DataAccess\Scope\EfectorSesionScopeChecker;
use common\components\Organization\DataAccess\Scope\EfectorSesionViaPesScopeChecker;
use common\components\Organization\Presentation\ProfesionalesConteoInfoPresentation;
use common\components\Organization\Presentation\ProfesionalesListadoRowsPresentation;
use common\components\Organization\Service\Authorization\OrganizationEfectorSesionPolicy;
use common\components\Organization\Service\Authorization\OrganizationPesEfectorPolicy;
use common\components\Organization\Service\Authorization\OrganizationPesOwnPolicy;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaFlowDraftHydrator;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioCrearFlowDraftHydrator;
use common\components\Person\DataAccess\Filter\SexoBiologicoFilterResolver;
use common\components\Person\DataAccess\Scope\PermitirParaSiMismoScopeChecker;
use common\components\Person\Representation\Assistant\PersonRepresentationUiActionCatalog;
use common\components\Scheduling\Home\Sections\AppointmentsDaySectionProvider;
use common\components\Scheduling\Home\Sections\PatientUpcomingAppointmentsSectionProvider;
use common\components\Scheduling\Home\Sections\SurgeriesDaySectionProvider;
use common\components\Scheduling\Service\Authorization\TurnoCreateSubjectPolicy;
use common\components\Scheduling\Service\Authorization\TurnoStaffEfectorBelongsPolicy;
use common\components\Scheduling\Service\Authorization\TurnoSubjectOrRepresentativePolicy;
use common\components\Scheduling\Service\ReservaTurnoTriageFlowDraftHydrator;
use common\components\Ui\Home\Service\Sections\ActionCardsSectionProvider;

return [
    'flowDraftHydrators' => [
        'organization.pes_crear_alta' => [ProfesionalEfectorServicioCrearFlowDraftHydrator::class, 'hydrateWithOptions'],
        'organization.pes_from_servicio' => [ProfesionalEfectorServicioAgendaFlowDraftHydrator::class, 'hydrateWithOptions'],
        'data_access.metric_flow' => [DataAccessFlowDraftHydrator::class, 'hydrateWithOptions'],
        'data_access.edit_flow' => [DataAccessEditFlowDraftHydrator::class, 'hydrateWithOptions'],
        'scheduling.reserva_triage' => [ReservaTurnoTriageFlowDraftHydrator::class, 'hydrateWithOptions'],
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

    'homePanelSectionProviders' => [
        'emergency_board' => EmergencyBoardSectionProvider::class,
        'emergency_indicators' => EmergencyIndicatorsSectionProvider::class,
        'appointments_day' => AppointmentsDaySectionProvider::class,
        'inpatients' => InpatientsSectionProvider::class,
        'surgeries_day' => SurgeriesDaySectionProvider::class,
        'action_cards' => ActionCardsSectionProvider::class,
        'patient_upcoming_appointments' => PatientUpcomingAppointmentsSectionProvider::class,
        'patient_care_plans_active' => PatientCarePlansActiveSectionProvider::class,
    ],
];
