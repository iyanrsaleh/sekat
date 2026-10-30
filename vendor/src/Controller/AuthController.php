<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\GoogleIdToken;
use App\Core\HttpException;
use App\Core\Role;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Repository\AuditRepository;
use App\Repository\EmailTokenRepository;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;

final class AuthController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly AuditRepository $audit,
        private readonly EmailTokenRepository $emailTokens,
        private readonly bool $debug = false,
        private readonly bool $cookieRefresh = false,
        private readonly string $googleClientId = '',
        private readonly string $googleClientSecret = '',
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function login(array $params, Request $request): Response
    {
        $input = $request->json();

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($email === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Email wajib diisi');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Format email tidak valid');
        }

        if ($password === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Password wajib diisi');
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            $this->audit->record('auth.login_failed', null, ['email' => $email]);

            throw new HttpException(401, 'INVALID_CREDENTIALS', 'Email atau password salah');
        }

        $this->audit->record('auth.login', (int) $user['id'], ['email' => $email]);

        return Response::json($this->tokenPayload((int) $user['id']));
    }

    /**
     * Client ID publik untuk tombol Google di halaman masuk dan daftar.
     *
     * @param array<string, string> $params
     */
    public function googleClient(array $params, Request $request): Response
    {
        return Response::json(['client_id' => $this->googleClientId]);
    }

    /**
     * Masuk atau buat akun dari ID token Google.
     *
     * @param array<string, string> $params
     */
    public function google(array $params, Request $request): Response
    {
        if ($this->googleClientId === '') {
            throw new HttpException(503, 'GOOGLE_NOT_CONFIGURED', 'Google OAuth belum diatur. Isi client_id di vendor/config/oauth.php');
        }

        $credential = trim((string) ($request->json()['credential'] ?? ''));

        if ($credential === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'credential wajib diisi');
        }

        $profile = GoogleIdToken::verify($credential, $this->googleClientId);
        $user = $this->users->findByEmail($profile['email']);
        $created = false;

        if ($user === null) {
            $user = $this->users->create($profile['name'], $profile['email'], bin2hex(random_bytes(24)), Role::USER, 'google');
            $created = true;
        } else {
            $this->users->markProvider((int) $user['id'], 'google');
        }

        $this->users->markEmailVerified((int) $user['id']);
        $this->audit->record($created ? 'auth.google_register' : 'auth.google_login', (int) $user['id'], ['email' => $profile['email']]);

        return Response::json($this->tokenPayload((int) $user['id']), $created ? 201 : 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function register(array $params, Request $request): Response
    {
        $input = $request->json();

        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($name === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Nama wajib diisi');
        }

        if (mb_strlen($name) > 120) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Nama maksimal 120 karakter');
        }

        if ($email === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Email wajib diisi');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Format email tidak valid');
        }

        if (strlen($password) < 8) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Password minimal 8 karakter');
        }

        $user = $this->users->create($name, $email, $password, Role::USER);
        $this->audit->record('auth.register', (int) $user['id'], ['email' => $email]);

        $verify = $this->emailTokens->issue((int) $user['id'], 'verify_email', 60);
        $link = 'verify-email?token=' . $verify['token'];
        Mailer::send($email, 'Verifikasi email', 'Buka: ' . $link);

        return Response::json([
            ...$this->tokenPayload((int) $user['id']),
            'email_verified' => false,
            ...($this->debug ? ['verify_token' => $verify['token']] : []),
        ], 201);
    }

    /**
     * Verifikasi alamat email memakai token.
     *
     * @param array<string, string> $params
     */
    public function verifyEmail(array $params, Request $request): Response
    {
        $input = $request->json();
        $token = (string) ($input['token'] ?? '');

        if ($token === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'token wajib diisi');
        }

        $row = $this->emailTokens->findValid($token, 'verify_email');

        if ($row === null) {
            throw new HttpException(400, 'INVALID_TOKEN', 'Token verifikasi tidak valid atau kedaluwarsa');
        }

        $this->emailTokens->consume((int) $row['id']);
        $this->users->markEmailVerified((int) $row['user_id']);
        $this->audit->record('auth.email_verified', (int) $row['user_id']);

        return Response::json(['verified' => true]);
    }

    /**
     * Meminta token reset password.
     *
     * @param array<string, string> $params
     */
    public function forgotPassword(array $params, Request $request): Response
    {
        $input = $request->json();
        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if ($email === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Email wajib diisi');
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return Response::json(['message' => 'Bila email terdaftar, tautan reset telah dikirim.']);
        }

        if (($user['provider'] ?? '') === 'google') {
            return Response::json(['message' => 'Akun ini masuk lewat Google. Tidak ada password untuk direset.']);
        }

        $issued = $this->emailTokens->issue((int) $user['id'], 'reset_password', 30);
        $this->audit->record('auth.forgot_password', (int) $user['id'], ['email' => $email]);

        $link = $this->publicUrl($request, 'reset-password?token=' . $issued['token']);

        try {
            Mailer::send($email, 'Reset password', 'Buka tautan ini untuk mengganti password: ' . $link);
        } catch (\RuntimeException) {
            throw new HttpException(503, 'MAIL_FAILED', 'Gmail menolak akun SMTP. Buat App Password baru, lalu ganti pass di vendor/config/mail.php.');
        }

        return Response::json([
            'message' => 'Bila email terdaftar, tautan reset telah dikirim.',
            ...($this->debug ? ['reset_token' => $issued['token']] : []),
        ]);
    }

    /**
     * Ganti password memakai token reset.
     *
     * @param array<string, string> $params
     */
    public function resetPassword(array $params, Request $request): Response
    {
        $input = $request->json();
        $token = (string) ($input['token'] ?? '');
        $password = (string) ($input['password'] ?? '');

        if ($token === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'token wajib diisi');
        }

        if (strlen($password) < 8) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Password minimal 8 karakter');
        }

        $row = $this->emailTokens->findValid($token, 'reset_password');

        if ($row === null) {
            throw new HttpException(400, 'INVALID_TOKEN', 'Token reset tidak valid atau kedaluwarsa');
        }

        $this->emailTokens->consume((int) $row['id']);
        $this->users->updatePassword((int) $row['user_id'], $password);
        $this->audit->record('auth.reset_password', (int) $row['user_id']);

        return Response::json(['reset' => true]);
    }

    /**
     * Memperbarui token memakai refresh token (rotasi).
     *
     * @param array<string, string> $params
     */
    public function refresh(array $params, Request $request): Response
    {
        $refreshToken = $this->cookieRefresh
            ? (string) $request->cookie('refresh_token', '')
            : (string) ($request->json()['refresh_token'] ?? '');

        if ($refreshToken === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'refresh_token wajib diisi');
        }

        $user = $this->tokens->findUserByRefreshToken($refreshToken);

        if ($user === null) {
            throw new HttpException(401, 'INVALID_REFRESH_TOKEN', 'Refresh token tidak valid atau kedaluwarsa');
        }

        $this->tokens->revoke($refreshToken);
        $this->audit->record('auth.refresh', (int) $user['id']);

        return Response::json($this->tokenPayload((int) $user['id']));
    }

    private function publicUrl(Request $request, string $path): string
    {
        $origin = Request::appOrigin();
        if ($request->header('x-forwarded-proto') === 'https') {
            $origin = (string) preg_replace('#^http://#', 'https://', $origin);
        }

        return $origin . '/' . ltrim($path, '/');
    }

    /**
     * @param array<string, string> $params
     */
    public function logout(array $params, Request $request): Response
    {
        $token = $request->attribute('token');

        if (is_string($token)) {
            $this->tokens->revoke($token);
        }

        $user = $request->attribute('user');
        $this->audit->record('auth.logout', is_array($user) ? (int) ($user['id'] ?? 0) : null);

        return Response::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(int $userId): array
    {
        $user = $this->users->find($userId);
        $access = $this->tokens->issue($userId, 'access', 7);
        $refresh = $this->tokens->issue($userId, 'refresh', 90);

        if ($this->cookieRefresh) {
            setcookie(
                'refresh_token',
                $refresh['token'],
                (new \DateTimeImmutable('+90 days'))->getTimestamp(),
                '/',
                '',
                false,
                true,
            );
        }

        return [
            'access_token' => $access['token'],
            ...($this->cookieRefresh ? ['cookie_refresh' => true] : ['refresh_token' => $refresh['token']]),
            'expires_in' => 604800,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}