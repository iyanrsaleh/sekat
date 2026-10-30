<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Upload gambar sederhana.
 * File disimpan di vendor/storage/uploads/{Tahun}/{Bulan}/.
 * Nama file dibuat ulang oleh server; nama asli user tidak dipakai.
 * Akses hanya lewat proxy API (tidak ada path statis ke folder upload).
 */
final class UploadController
{
    private const MAX_BYTES = 2 * 1024 * 1024;   // gambar
    private const MAX_DOC_BYTES = 5 * 1024 * 1024; // dokumen (PDF)

    private const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function store(array $params, Request $request): Response
    {
        return Response::json($this->save($request), 201);
    }

    /**
     * Menyimpan file dan mengembalikan metadata. Dipakai store() dan avatar profil.
     *
     * @param array<int, string>|null $allowedExtensions batasi ekstensi (mis. ['png','jpg','webp'])
     * @return array{name: string, year_month: string, url: string, mime: string, size: int}
     */
    public function save(Request $request, ?array $allowedExtensions = null): array
    {
        $file = $request->file('file');

        if ($file === null) {
            throw new HttpException(422, 'FILE_REQUIRED', 'File wajib dikirim pada field "file"');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'UPLOAD_FAILED', 'Upload gagal (kode ' . $error . ')');
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        $mime = $temporary !== '' ? (string) mime_content_type($temporary) : '';

        if (!isset(self::TYPES[$mime])) {
            throw new HttpException(422, 'FILE_TYPE_NOT_ALLOWED', 'Hanya PNG, JPEG, WEBP, atau PDF');
        }

        $extension = self::TYPES[$mime];

        if ($allowedExtensions !== null && !in_array($extension, $allowedExtensions, true)) {
            throw new HttpException(422, 'FILE_TYPE_NOT_ALLOWED', 'Hanya ' . strtoupper(implode('/', $allowedExtensions)));
        }

        $maxBytes = $extension === 'pdf' ? self::MAX_DOC_BYTES : self::MAX_BYTES;
        $maxLabel = $extension === 'pdf' ? '5 MB' : '2 MB';

        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new HttpException(422, 'FILE_TOO_LARGE', 'Ukuran file maksimal ' . $maxLabel);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $yearMonth = date('Y') . '/' . date('m');
        $dir = $this->directory . '/' . $yearMonth;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'UPLOAD_FAILED', 'Folder upload tidak bisa dibuat');
        }

        if (!move_uploaded_file($temporary, $dir . '/' . $name)) {
            throw new HttpException(500, 'UPLOAD_FAILED', 'Gagal menyimpan file');
        }

        return [
            'name' => $name,
            'year_month' => $yearMonth,
            'url' => 'api/uploads/' . $yearMonth . '/' . $name,
            'mime' => $mime,
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): Response
    {
        $path = $this->resolve($params);

        if ($path === null || !is_file($path)) {
            return Response::error('UPLOAD_NOT_FOUND', 'File tidak ditemukan', 404);
        }

        return Response::file($path, (string) (mime_content_type($path) ?: 'application/octet-stream'));
    }

    /**
     * Daftar file upload (admin).
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $items = [];

        foreach (glob($this->directory . '/*/*/*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $name = basename($file);
            $subdir = trim(str_replace([$this->directory, '\\'], ['', '/'], dirname($file)), '/');

            $items[] = [
                'name' => $name,
                'year_month' => $subdir,
                'url' => 'api/uploads/' . $subdir . '/' . $name,
                'size' => (int) filesize($file),
                'mtime' => date(DATE_ATOM, (int) filemtime($file)),
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(array $params): Response
    {
        $path = $this->resolve($params);

        if ($path === null || !is_file($path)) {
            return Response::error('UPLOAD_NOT_FOUND', 'File tidak ditemukan', 404);
        }

        unlink($path);

        return Response::noContent();
    }

    /**
     * Memvalidasi parameter {year}/{month}/{name} dan mengembalikan path fisik.
     *
     * @param array<string, string> $params
     */
    private function resolve(array $params): ?string
    {
        $year = (string) ($params['year'] ?? '');
        $month = (string) ($params['month'] ?? '');
        $name = (string) ($params['name'] ?? '');

        if (preg_match('/^\d{4}$/', $year) !== 1) {
            return null;
        }

        if (preg_match('/^(0[1-9]|1[0-2])$/', $month) !== 1) {
            return null;
        }

        if (preg_match('/^[a-f0-9]{32}\.(png|jpg|webp|pdf)$/', $name) !== 1) {
            return null;
        }

        return $this->directory . '/' . $year . '/' . $month . '/' . $name;
    }
}