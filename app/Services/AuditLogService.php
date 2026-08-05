<?php

namespace App\Services;

use App\Repositories\AuditLogRepository;

class AuditLogService
{
    public function __construct(private AuditLogRepository $repo) {}

    public function list(array $filters = [], int $limit = 150): array
    {
        return $this->repo->recent($limit, [
            'module' => trim($filters['module'] ?? ''),
            'action' => trim($filters['action'] ?? ''),
            'q'      => trim($filters['q'] ?? ''),
        ]);
    }

    public function filterOptions(): array
    {
        return [
            'modules' => $this->repo->modules(),
            'actions' => $this->repo->actions(),
            'total'   => $this->repo->count(),
        ];
    }
}
