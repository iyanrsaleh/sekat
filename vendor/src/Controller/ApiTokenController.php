<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\ApiTokenRepository;

/**
 * Pengelola token API (admin): buat dengan masa berlaku, daftar, cabut, grant.
 */
final class ApiTokenController
{
    public function __construct(private readonly ApiTokenRepository $tokens)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function store(array $params, Request $request): Response
    {
        $input = $request->json();

        $name = strtolower(trim((string) ($input['name'] ?? '')));
        $secret = isset($input['token']) ? (string) $input['token'] : null;
        $expires = $this->expiry($input);

        $token = $this->tokens->create($name, $secret, $expires);

        return Response::json($token, 201);
    }

    public function index(): Response
    {
        return Response::json(['items' => $this->tokens->all()]);
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(array $params): Response
    {
        if (!$this->tokens->revoke((string) ($params['name'] ?? ''))) {
            return Response::error('TOKEN_NOT_FOUND', 'Nama token tidak ada', 404);
        }

        return Response::noContent();
    }

    public function grantsIndex(): Response
    {
        return Response::json(['items' => $this->tokens->grants()]);
    }

    /**
     * @param array<string, string> $params
     */
    public function grantStore(array $params, Request $request): Response
    {
        $input = $request->json();

        $this->tokens->grant(
            (string) ($input['token'] ?? ''),
            (string) ($input['kind'] ?? 'api'),
            (string) ($input['name'] ?? ''),
        );

        return Response::json(['granted' => true], 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function grantDestroy(array $params): Response
    {
        if (!$this->tokens->revokeGrant(
            (string) ($params['name'] ?? ''),
            (string) ($params['kind'] ?? ''),
            (string) ($params['resource'] ?? ''),
        )) {
            return Response::error('GRANT_NOT_FOUND', 'Grant tidak ada', 404);
        }

        return Response::noContent();
    }

    /**
     * Mengubah days/months/years menjadi waktu kadaluwarsa. Wajib satu saja.
     *
     * @param array<string, mixed> $input
     */
    private function expiry(array $input): ?\DateTimeImmutable
    {
        $fields = ['days' => 'days', 'months' => 'months', 'years' => 'years'];
        $given = [];

        foreach ($fields as $field => $unit) {
            if (array_key_exists($field, $input)) {
                $given[$unit] = (int) $input[$field];
            }
        }

        if (count($given) > 1) {
            throw new HttpException(400, 'VALIDATION_ERROR', 'Isi salah satu: days, months, atau years');
        }

        if ($given === []) {
            return null;
        }

        [$unit, $amount] = [array_key_first($given), current($given)];

        if ($amount < 1) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Nilai ' . $unit . ' minimal 1');
        }

        return (new \DateTimeImmutable('+' . $amount . ' ' . $unit));
    }
}