<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Memotong/menyusutkan gambar ke lebar×tinggi tetap (cover, center crop).
 */
final class ImageResizer
{
    /**
     * @return bool true bila berkas tujuan berhasil ditulis
     */
    public function cover(string $sourcePath, string $destPath, string $extension, int $width, int $height): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new HttpException(500, 'RESIZE_UNAVAILABLE', 'Resize membutuhkan ekstensi GD');
        }

        $width = max(32, min(4096, $width));
        $height = max(32, min(4096, $height));

        $source = $this->open($sourcePath, $extension);
        $srcW = imagesx($source);
        $srcH = imagesy($source);

        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);
            throw new HttpException(500, 'RESIZE_FAILED', 'Gambar sumber tidak valid');
        }

        $scale = max($width / $srcW, $height / $srcH);
        $cropW = (int) max(1, round($width / $scale));
        $cropH = (int) max(1, round($height / $scale));
        $srcX = (int) max(0, (int) floor(($srcW - $cropW) / 2));
        $srcY = (int) max(0, (int) floor(($srcH - $cropH) / 2));

        $dest = imagecreatetruecolor($width, $height);
        $this->preserveAlpha($dest, $extension);
        imagecopyresampled($dest, $source, 0, 0, $srcX, $srcY, $width, $height, $cropW, $cropH);
        imagedestroy($source);

        $ok = $this->save($dest, $destPath, $extension);
        imagedestroy($dest);

        return $ok;
    }

    private function open(string $path, string $extension): \GdImage
    {
        $image = match ($extension) {
            'jpg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($image === false) {
            throw new HttpException(500, 'RESIZE_FAILED', 'Gambar tidak bisa dibaca untuk resize');
        }

        return $image;
    }

    private function preserveAlpha(\GdImage $image, string $extension): void
    {
        if ($extension === 'jpg') {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $clear);
    }

    private function save(\GdImage $image, string $path, string $extension): bool
    {
        return match ($extension) {
            'jpg' => imagejpeg($image, $path, 85),
            'png' => imagepng($image, $path, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $path, 85) : false,
            default => false,
        };
    }
}
