<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\AuditRepository;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;

final class MeController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UploadController $uploads,
        private readonly AuditRepository $audit,
        private readonly TokenRepository $tokens,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params, Request $request): Response
    {
        return Response::json($request->attribute('user'));
    }

    /**
     * Ubah nama & email sendiri.
     *
     * @param array<string, string> $params
     */
    public function updateProfile(array $params, Request $request): Response
    {
        $user = $request->attribute('user');

        if (!is_array($user)) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak valid');
        }

        $input = $request->json();
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $currentEmail = strtolower(trim((string) ($user['email'] ?? '')));

        if ($name === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Nama wajib diisi');
        }

        if (mb_strlen($name) > 120) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Nama maksimal 120 karakter');
        }

        if ($email !== '' && $email !== $currentEmail) {
            throw new HttpException(422, 'EMAIL_LOCKED', 'Alamat email tidak dapat diubah');
        }

        $this->users->updateProfile((int) $user['id'], $name, $currentEmail);
        $this->audit->record('user.profile', (int) $user['id']);

        return Response::json($this->users->find((int) $user['id']));
    }

    /**
     * Logout dari semua perangkat (cabut semua token user).
     *
     * @param array<string, string> $params
     */
    public function logoutAll(array $params, Request $request): Response
    {
        $user = $request->attribute('user');

        if (!is_array($user)) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak valid');
        }

        $this->tokens->revokeAllForUser((int) $user['id']);
        $this->audit->record('user.logout_all', (int) $user['id']);

        return Response::noContent();
    }

    /**
     * Ganti foto profil (multipart field "file", gambar saja).
     *
     * @param array<string, string> $params
     */
    public function updateAvatar(array $params, Request $request): Response
    {
        $user = $request->attribute('user');

        if (!is_array($user)) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak valid');
        }

        $saved = $this->uploads->save($request, ['png', 'jpg', 'webp']);
        $this->users->setAvatar((int) $user['id'], $saved['url']);
        $this->audit->record('user.avatar', (int) $user['id']);

        return Response::json(['avatar' => $saved['url']]);
    }

    /**
     * Ubah password sendiri.
     *
     * @param array<string, string> $params
     */
    public function changePassword(array $params, Request $request): Response
    {
        $user = $request->attribute('user');

        if (!is_array($user)) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak valid');
        }

        $input = $request->json();
        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');

        if ($current === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Password saat ini wajib diisi');
        }

        if (strlen($new) < 8) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Password baru minimal 8 karakter');
        }

        if ($new === $current) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Password baru harus berbeda');
        }

        $hash = $this->users->passwordHash((int) $user['id']);
        $account = $this->users->find((int) $user['id']);

        if (is_array($account) && ($account['provider'] ?? '') === 'google') {
            throw new HttpException(422, 'GOOGLE_ACCOUNT', 'Akun Google tidak memakai password di sini');
        }

        if ($hash === null || !password_verify($current, $hash)) {
            throw new HttpException(422, 'INVALID_PASSWORD', 'Password saat ini salah');
        }

        $this->users->updatePassword((int) $user['id'], $new);
        $this->audit->record('user.password', (int) $user['id']);

        return Response::json(['changed' => true]);
    }
}