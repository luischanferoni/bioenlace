<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\Enum\RequestStatus;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;
use common\models\DiagnosticoConsulta;

/**
 * Anti-duplicado de evoluciones en episodio (INTERNACION / GUARDIA):
 * aviso si la nota se parece a un pase previo + ítems ya activos en el episodio.
 */
final class EpisodeCaptureDedupService
{
    public const NOTE_SIMILARITY_THRESHOLD = 0.92;

    public const ADVISORY_NOTE_DUPLICATE = 'episode_note_duplicate';
    public const ADVISORY_ITEMS_ACTIVE = 'episode_items_already_active';

    /**
     * @param list<array<string, mixed>> $categories capture_review.categories
     * @return array{
     *   applies: bool,
     *   note_duplicate: bool,
     *   duplicate_item_ids: list<string>,
     *   advisories: list<array{code: string, severity: string, message: string}>
     * }
     */
    public function analyze(
        string $parent,
        int $parentId,
        int $subjectPersonaId,
        string $texto,
        array $categories = []
    ): array {
        $parent = strtoupper(trim($parent));
        $empty = [
            'applies' => false,
            'note_duplicate' => false,
            'duplicate_item_ids' => [],
            'advisories' => [],
        ];
        if (
            !in_array($parent, [Encounter::PARENT_INTERNACION, Encounter::PARENT_GUARDIA], true)
            || $parentId <= 0
            || $subjectPersonaId <= 0
        ) {
            return $empty;
        }

        $encounterIds = $this->listEpisodeEncounterIds($parent, $parentId, $subjectPersonaId);
        $noteDuplicate = $this->isNoteDuplicateOfPriorEvolutions(
            $texto,
            $parent,
            $parentId,
            $subjectPersonaId
        );
        $duplicateItemIds = $this->findAlreadyActiveItemIds($categories, $encounterIds, $subjectPersonaId);

        $advisories = [];
        if ($noteDuplicate) {
            $advisories[] = [
                'code' => self::ADVISORY_NOTE_DUPLICATE,
                'severity' => 'warning',
                'message' => 'Esta nota es muy parecida a una evolución previa del mismo episodio. '
                    . 'Conviene documentar los cambios clínicos; si confirmás, se guarda igual.',
            ];
        }
        if ($duplicateItemIds !== []) {
            $advisories[] = [
                'code' => self::ADVISORY_ITEMS_ACTIVE,
                'severity' => 'warning',
                'message' => 'Algunos ítems ya están activos en el episodio: no se tildan por defecto '
                    . 'y no se volverán a persistir si los confirmás sin cambios.',
            ];
        }

        return [
            'applies' => true,
            'note_duplicate' => $noteDuplicate,
            'duplicate_item_ids' => $duplicateItemIds,
            'advisories' => $advisories,
        ];
    }

    /**
     * Aplica el resultado al capture_review (aviso de nota + destildar ya activos).
     * La nota parecida no bloquea confirmar: es advisory.
     *
     * @param array<string, mixed> $review
     * @param array<string, mixed> $dedup
     * @return array<string, mixed>
     */
    public function applyToReview(array $review, array $dedup): array
    {
        if (($dedup['applies'] ?? false) !== true) {
            return $review;
        }

        $duplicateIds = [];
        foreach ($dedup['duplicate_item_ids'] ?? [] as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $duplicateIds[$id] = true;
            }
        }

        $categories = [];
        foreach ($review['categories'] ?? [] as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $items = [];
            foreach ($cat['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemId = (string) ($item['id'] ?? '');
                if ($itemId !== '' && isset($duplicateIds[$itemId])) {
                    $item['already_active'] = true;
                    $sub = trim((string) ($item['subtitle'] ?? ''));
                    $tag = 'Ya activo en el episodio';
                    if ($sub === '') {
                        $item['subtitle'] = $tag;
                    } elseif (stripos($sub, $tag) === false) {
                        $item['subtitle'] = $sub . ' · ' . $tag;
                    }
                }
                $items[] = $item;
            }
            $cat['items'] = $items;
            $categories[] = $cat;
        }
        $review['categories'] = $categories;

        $staged = [];
        foreach ($review['default_staged_item_ids'] ?? [] as $id) {
            $id = (string) $id;
            if ($id === '' || isset($duplicateIds[$id])) {
                continue;
            }
            $staged[] = $id;
        }
        $review['default_staged_item_ids'] = $staged;

        $advisories = [];
        foreach ($dedup['advisories'] ?? [] as $adv) {
            if (is_array($adv) && trim((string) ($adv['message'] ?? '')) !== '') {
                $advisories[] = $adv;
            }
        }
        if ($advisories !== []) {
            $review['advisories'] = $advisories;
        }

        return $review;
    }

    public function isNoteDuplicateOfPriorEvolutions(
        string $texto,
        string $parent,
        int $parentId,
        int $subjectPersonaId
    ): bool {
        $normalized = $this->normalizeNote($texto);
        if ($normalized === '' || mb_strlen($normalized) < 40) {
            return false;
        }
        $hash = hash('sha256', $normalized);
        foreach ($this->listPriorNotes($parent, $parentId, $subjectPersonaId) as $prior) {
            $priorNorm = $this->normalizeNote($prior);
            if ($priorNorm === '') {
                continue;
            }
            if (hash('sha256', $priorNorm) === $hash) {
                return true;
            }
            similar_text($normalized, $priorNorm, $percent);
            if (($percent / 100.0) >= self::NOTE_SIMILARITY_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function listEpisodeEncounterIds(string $parent, int $parentId, int $subjectPersonaId): array
    {
        $parent = strtoupper(trim($parent));
        $rows = Encounter::find()
            ->select(['id'])
            ->where([
                'parent_id' => $parentId,
                'subject_persona_id' => $subjectPersonaId,
                'deleted_at' => null,
            ])
            ->andWhere([
                'or',
                ['parent_type' => $parent],
                ['parent_type' => Encounter::PARENT_CLASSES[$parent] ?? '__none__'],
            ])
            ->asArray()
            ->all();
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function hasActiveConditionKey(int $subjectPersonaId, array $encounterIds, string $dedupeKey): bool
    {
        if ($dedupeKey === '' || $subjectPersonaId <= 0) {
            return false;
        }
        $presentation = new ConditionPresentationService();
        $q = Condition::find()
            ->andWhere(['subject_persona_id' => $subjectPersonaId])
            ->andWhere(['deleted_at' => null])
            ->andWhere(['clinical_status' => DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE]);
        if ($encounterIds !== []) {
            $q->andWhere(['encounter_id' => $encounterIds]);
        }
        foreach ($q->all() as $cond) {
            if (!$cond instanceof Condition) {
                continue;
            }
            if ($presentation->dedupeKeyForCondition($cond) === $dedupeKey) {
                return true;
            }
        }

        return false;
    }

    public function hasActiveIndicationKey(array $encounterIds, string $key): bool
    {
        return $key !== '' && $encounterIds !== [] && isset($this->activeIndicationKeys($encounterIds)[$key]);
    }

    public function hasActiveMedicationDisplay(array $encounterIds, string $displayKey): bool
    {
        if ($displayKey === '' || $encounterIds === []) {
            return false;
        }
        $rows = MedicationRequest::find()
            ->select(['medication_display'])
            ->andWhere(['encounter_id' => $encounterIds])
            ->andWhere(['status' => RequestStatus::ACTIVE])
            ->andWhere(['deleted_at' => null])
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            if ($this->normalizeKey((string) ($row['medication_display'] ?? '')) === $displayKey) {
                return true;
            }
        }

        return false;
    }

    public function normalizeKey(string $value): string
    {
        $v = mb_strtolower(trim($value), 'UTF-8');
        $v = strtr($v, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $v = preg_replace('/[^a-z0-9]+/u', '', $v) ?? $v;

        return $v;
    }

    public function normalizeNote(string $texto): string
    {
        $v = mb_strtolower(trim($texto), 'UTF-8');
        $v = strtr($v, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

        return trim($v);
    }

    /**
     * @return list<string>
     */
    private function listPriorNotes(string $parent, int $parentId, int $subjectPersonaId): array
    {
        $parent = strtoupper(trim($parent));
        $rows = Encounter::find()
            ->select(['note', 'reason_text', 'status'])
            ->where([
                'parent_id' => $parentId,
                'subject_persona_id' => $subjectPersonaId,
                'deleted_at' => null,
            ])
            ->andWhere([
                'or',
                ['parent_type' => $parent],
                ['parent_type' => Encounter::PARENT_CLASSES[$parent] ?? '__none__'],
            ])
            ->andWhere(['<>', 'status', EncounterStatus::IN_PROGRESS])
            ->orderBy(['id' => SORT_DESC])
            ->limit(12)
            ->asArray()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $note = trim((string) ($row['note'] ?? ''));
            if ($note === '') {
                $note = trim((string) ($row['reason_text'] ?? ''));
            }
            if ($note !== '') {
                $out[] = $note;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $categories
     * @param list<int> $encounterIds
     * @return list<string>
     */
    private function findAlreadyActiveItemIds(
        array $categories,
        array $encounterIds,
        int $subjectPersonaId
    ): array {
        if ($categories === [] || $encounterIds === []) {
            return [];
        }
        $presentation = new ConditionPresentationService();
        $activeMedKeys = $this->activeMedicationKeys($encounterIds);
        $activeIndicationKeys = $this->activeIndicationKeys($encounterIds);
        $dupIds = [];

        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $model = (string) ($cat['model'] ?? '');
            $title = mb_strtolower((string) ($cat['title'] ?? ''), 'UTF-8');
            foreach ($cat['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemId = (string) ($item['id'] ?? '');
                if ($itemId === '') {
                    continue;
                }
                $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
                if ($this->isConditionCategory($model, $title)) {
                    $label = (string) (
                        $payload['termino']
                        ?? $payload['texto']
                        ?? $payload['descripcion']
                        ?? $item['label']
                        ?? ''
                    );
                    $code = (string) ($payload['codigo'] ?? $payload['codigo_cie10'] ?? '');
                    $key = $presentation->dedupeKeyForLabel($label, $code);
                    if ($key !== '' && $this->hasActiveConditionKey($subjectPersonaId, $encounterIds, $key)) {
                        $dupIds[] = $itemId;
                    }
                    continue;
                }
                if ($this->isMedicationCategory($model, $title)) {
                    $display = (string) (
                        $payload['Nombre del medicamento']
                        ?? $payload['medication_display']
                        ?? $payload['display']
                        ?? $item['label']
                        ?? ''
                    );
                    $key = $this->normalizeKey($display);
                    if ($key !== '' && isset($activeMedKeys[$key])) {
                        $dupIds[] = $itemId;
                    }
                    continue;
                }
                if ($this->isIndicationCategory($model, $title)) {
                    $ind = (string) (
                        $payload['Indicacion']
                        ?? $payload['indicacion']
                        ?? $payload['display']
                        ?? $item['label']
                        ?? ''
                    );
                    $tipo = (string) ($payload['Tipo'] ?? $payload['tipo'] ?? '');
                    $key = $this->normalizeKey($ind . '|' . $tipo);
                    if ($key !== '' && isset($activeIndicationKeys[$key])) {
                        $dupIds[] = $itemId;
                    }
                }
            }
        }

        return array_values(array_unique($dupIds));
    }

    /**
     * Quita filas que el persist ya salta (condición/med/indicación activa en el episodio).
     * Así la completitud no exige frecuencia/plazo de ítems destildados «ya activos».
     *
     * @param array<string, mixed> $extraidos
     * @param list<array<string, mixed>> $categorias
     * @return array<string, mixed>
     */
    public function dropAlreadyActiveRows(
        array $extraidos,
        array $categorias,
        string $parent,
        int $parentId,
        int $subjectPersonaId
    ): array {
        $parent = strtoupper(trim($parent));
        if (
            $extraidos === []
            || $parentId <= 0
            || $subjectPersonaId <= 0
            || !in_array($parent, [Encounter::PARENT_INTERNACION, Encounter::PARENT_GUARDIA], true)
        ) {
            return $extraidos;
        }
        $encounterIds = $this->listEpisodeEncounterIds($parent, $parentId, $subjectPersonaId);
        if ($encounterIds === []) {
            return $extraidos;
        }

        $presentation = new ConditionPresentationService();
        $activeMedKeys = $this->activeMedicationKeys($encounterIds);
        $activeIndicationKeys = $this->activeIndicationKeys($encounterIds);
        $out = $extraidos;

        foreach ($categorias as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $title = trim((string) ($cat['titulo'] ?? $cat['title'] ?? ''));
            $model = (string) ($cat['modelo'] ?? $cat['model'] ?? '');
            $titleLower = mb_strtolower($title, 'UTF-8');
            $key = $this->resolveExtraidosKey($out, $title, $model);
            if ($key === null) {
                continue;
            }
            $rows = $this->normalizeExtractedRows($out[$key] ?? null);
            $kept = [];
            foreach ($rows as $row) {
                if ($this->extractedRowIsAlreadyActive(
                    $row,
                    $model,
                    $titleLower,
                    $subjectPersonaId,
                    $encounterIds,
                    $presentation,
                    $activeMedKeys,
                    $activeIndicationKeys
                )) {
                    continue;
                }
                $kept[] = $row;
            }
            if ($kept === []) {
                unset($out[$key]);
            } else {
                $out[$key] = $kept;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $extraidos
     */
    private function resolveExtraidosKey(array $extraidos, string $title, string $modelo): ?string
    {
        foreach ([$title, $modelo] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && array_key_exists($candidate, $extraidos)) {
                return $candidate;
            }
        }
        $want = [];
        foreach ([$title, $modelo] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                $want[$this->normalizeKey($candidate)] = true;
            }
        }
        if ($want === []) {
            return null;
        }
        foreach ($extraidos as $k => $_) {
            if (is_string($k) && isset($want[$this->normalizeKey($k)])) {
                return $k;
            }
        }

        return null;
    }

    /**
     * @param mixed $raw
     * @return list<mixed>
     */
    private function normalizeExtractedRows($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            return trim($raw) !== '' ? [trim($raw)] : [];
        }
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        $i = 0;
        foreach (array_keys($raw) as $k) {
            if ($k !== $i) {
                return [$raw];
            }
            $i++;
        }

        return array_values($raw);
    }

    /**
     * @param mixed $row
     * @param array<string, true> $activeMedKeys
     * @param array<string, true> $activeIndicationKeys
     */
    private function extractedRowIsAlreadyActive(
        $row,
        string $model,
        string $titleLower,
        int $subjectPersonaId,
        array $encounterIds,
        ConditionPresentationService $presentation,
        array $activeMedKeys,
        array $activeIndicationKeys
    ): bool {
        $payload = is_array($row) ? $row : ['texto' => is_string($row) ? $row : ''];
        if ($this->isConditionCategory($model, $titleLower)) {
            $label = (string) (
                $payload['termino']
                ?? $payload['texto']
                ?? $payload['descripcion']
                ?? $payload['display']
                ?? ''
            );
            $code = (string) ($payload['codigo'] ?? $payload['codigo_cie10'] ?? '');
            $key = $presentation->dedupeKeyForLabel($label, $code);
            return $key !== '' && $this->hasActiveConditionKey($subjectPersonaId, $encounterIds, $key);
        }
        if ($this->isMedicationCategory($model, $titleLower)) {
            $display = (string) (
                $payload['Nombre del medicamento']
                ?? $payload['medication_display']
                ?? $payload['display']
                ?? $payload['texto']
                ?? ''
            );
            $key = $this->normalizeKey($display);
            return $key !== '' && isset($activeMedKeys[$key]);
        }
        if ($this->isIndicationCategory($model, $titleLower)) {
            $ind = (string) (
                $payload['Indicacion']
                ?? $payload['indicacion']
                ?? $payload['display']
                ?? $payload['texto']
                ?? ''
            );
            $tipo = (string) ($payload['Tipo'] ?? $payload['tipo'] ?? '');
            $key = $this->normalizeKey($ind . '|' . $tipo);
            return $key !== '' && isset($activeIndicationKeys[$key]);
        }

        return false;
    }

    /**
     * @param list<int> $encounterIds
     * @return array<string, true>
     */
    private function activeMedicationKeys(array $encounterIds): array
    {
        $keys = [];
        $rows = MedicationRequest::find()
            ->select(['medication_display'])
            ->andWhere(['encounter_id' => $encounterIds])
            ->andWhere(['status' => RequestStatus::ACTIVE])
            ->andWhere(['deleted_at' => null])
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            $k = $this->normalizeKey((string) ($row['medication_display'] ?? ''));
            if ($k !== '') {
                $keys[$k] = true;
            }
        }

        return $keys;
    }

    /**
     * @param list<int> $encounterIds
     * @return array<string, true>
     */
    private function activeIndicationKeys(array $encounterIds): array
    {
        $keys = [];
        if (!class_exists(ServiceRequest::class)) {
            return $keys;
        }
        $rows = ServiceRequest::find()
            ->select(['display', 'code', 'category'])
            ->andWhere(['encounter_id' => $encounterIds])
            ->andWhere(['deleted_at' => null])
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            $display = (string) ($row['display'] ?? $row['code'] ?? '');
            $cat = (string) ($row['category'] ?? '');
            $k = $this->normalizeKey($display . '|' . $cat);
            if ($k !== '') {
                $keys[$k] = true;
            }
        }

        return $keys;
    }

    private function isConditionCategory(string $model, string $titleLower): bool
    {
        if (in_array($model, ['DiagnosticoConsulta', 'ConsultaOdontologiaDiagnosticos'], true)) {
            return true;
        }

        return str_contains($titleLower, 'evoluci') || str_contains($titleLower, 'diagn');
    }

    private function isMedicationCategory(string $model, string $titleLower): bool
    {
        if ($model === 'ConsultaMedicamentos') {
            return true;
        }

        return str_contains($titleLower, 'medic');
    }

    private function isIndicationCategory(string $model, string $titleLower): bool
    {
        if ($model === 'ConsultaIndicaciones') {
            return true;
        }

        return str_contains($titleLower, 'indicacion') || str_contains($titleLower, 'indicaciones');
    }
}
