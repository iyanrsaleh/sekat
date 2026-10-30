<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Cache;
use App\Core\Exporter;
use App\Core\Request;
use App\Core\Response;
use App\Repository\AuditRepository;
use App\Repository\CrudRepository;

/**
 * Controller generik untuk semua Model.
 * Route /api/{resource} langsung dilayani tanpa controller per fitur.
 */
final class ResourceController
{
    /**
     * @param class-string<\App\Core\Model> $model
     */
    public function __construct(
        private readonly string $model,
        private readonly CrudRepository $repository,
        private readonly ?AuditRepository $audit = null,
    ) {
    }

    /**
     * Mendukung ?page=1&per_page=20&sort=id&order=desc dan filter per field.
     *
     * @param array<string, string> $params
     */
    public function index(array $params, Request $request): Response
    {
        $filters = [];

        foreach ($this->model::fieldNames() as $field) {
            $value = $request->query($field);

            if ($value !== null && $value !== '') {
                $filters[$field] = (string) $value;
            }
        }

        $arguments = [
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 20),
            $filters,
            (string) $request->query('sort', 'id'),
            (string) $request->query('order', 'desc'),
            $this->with($request),
        ];

        $namespace = 'crud/' . $this->model::resource();
        $cacheKey = (string) json_encode($arguments);

        $data = Cache::remember($namespace, $cacheKey, 15, function () use ($arguments): array {
            return $this->repository->paginate(...$arguments);
        });

        return Response::json($data);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params, Request $request): Response
    {
        return $this->found((int) ($params['id'] ?? 0), $this->with($request));
    }

    /**
     * Ekspor data ke CSV/XLSX (admin). ?format=csv|xlsx, ?per_page=jumlah.
     *
     * @param array<string, string> $params
     */
    public function export(array $params, Request $request): Response
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $perPage = min(5000, max(1, (int) $request->query('per_page', 500)));

        $headers = [];

        foreach ($this->model::fieldNames() as $field) {
            $headers[$field] = (string) ($this->model::fields()[$field]['label'] ?? $field);
        }

        $result = $this->repository->paginate(1, $perPage, [], 'id', 'desc');
        $rows = $result['items'];

        if ($format === 'xlsx') {
            $filename = $this->model::resource() . '-' . date('Ymd-His') . '.xlsx';

            return Response::raw(Exporter::xlsx($rows, $headers), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        $filename = $this->model::resource() . '-' . date('Ymd-His') . '.csv';

        return Response::raw(Exporter::csv($rows, $headers), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(array $params, Request $request): Response
    {
        Cache::flush('crud/' . $this->model::resource());

        $row = $this->repository->create($request->json());
        $this->auditRecord($request, $row['id'] ?? null, 'create');

        return Response::json($row, 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(array $params, Request $request): Response
    {
        $id = (int) ($params['id'] ?? 0);

        if ($this->repository->find($id) === null) {
            return $this->missing();
        }

        Cache::flush('crud/' . $this->model::resource());

        $row = $this->repository->update($id, $request->json());
        $this->auditRecord($request, $id, 'update');

        return Response::json($row);
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(array $params, Request $request): Response
    {
        $force = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);

        if (!$this->repository->delete((int) ($params['id'] ?? 0), $force)) {
            return $this->missing();
        }

        Cache::flush('crud/' . $this->model::resource());
        $this->auditRecord($request, (int) ($params['id'] ?? 0), $force ? 'force_delete' : 'delete');

        return Response::noContent();
    }

    /**
     * @param array<string, string> $params
     */
    public function restore(array $params, Request $request): Response
    {
        $row = $this->repository->restore((int) ($params['id'] ?? 0));

        if ($row === null) {
            return $this->missing();
        }

        Cache::flush('crud/' . $this->model::resource());
        $this->auditRecord($request, $row['id'] ?? null, 'restore');

        return Response::json($row);
    }

    private function auditRecord(Request $request, int|string|null $id, string $action): void
    {
        if ($this->audit === null) {
            return;
        }

        $user = $request->attribute('user');

        $this->audit->record(
            $this->model::resource() . '.' . $action,
            is_array($user) ? (int) ($user['id'] ?? 0) : null,
            [$id === null ? [] : ['id' => (int) $id]],
        );
    }

    /**
     * @return array<int, string>
     */
    private function with(Request $request): array
    {
        $raw = trim((string) $request->query('with', ''));

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * @param array<int, string> $with
     */
    private function found(int $id, array $with = []): Response
    {
        $row = $this->repository->find($id, $with);

        return $row === null ? $this->missing() : Response::json($row);
    }

    private function missing(): Response
    {
        $short = strtoupper(substr((string) strrchr($this->model, '\\'), 1));

        return Response::error($short . '_NOT_FOUND', 'Data tidak ditemukan', 404);
    }
}