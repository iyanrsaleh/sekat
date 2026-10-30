<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Repository\AuditRepository;
use App\Repository\SettingRepository;

/**
 * Pengaturan dasar aplikasi (admin). Nilai bukan rahasia.
 */
final class SettingsController
{
    public function __construct(
        private readonly SettingRepository $settings,
        private readonly ?AuditRepository $audit = null,
    ) {
    }

    public function index(): Response
    {
        return Response::json($this->settings->all());
    }

    public function publicIndex(): Response
    {
        return Response::json($this->settings->publicValues());
    }

    /**
     * @param array<string, string> $params
     */
    public function update(array $params, Request $request): Response
    {
        $values = $this->settings->update($request->json());

        $user = $request->attribute('user');

        $this->audit?->record(
            'settings.update',
            is_array($user) ? (int) ($user['id'] ?? 0) : null,
            ['keys' => array_keys($request->json())],
        );

        return Response::json($values);
    }
}