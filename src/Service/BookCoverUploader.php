<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

final class BookCoverUploader
{
    private string $projectDir;
    public function __construct(
        KernelInterface $kernel
    ) {
        $this->projectDir = $kernel->getProjectDir();
    }

    /**
     * Enregistre la couverture sous assets/img/covers/{id}.jpg
     */
    public function upload(UploadedFile $file, int $bookId): void
    {
        $dir = $this->projectDir.'/assets/img/covers';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $targetPath = $dir.'/'.$bookId.'.jpg';
        $mime = $file->getMimeType();

        if ('image/jpeg' === $mime || 'image/jpg' === $mime) {
            $file->move($dir, $bookId.'.jpg');

            return;
        }

        if (!extension_loaded('gd')) {
            throw new \RuntimeException('L’extension PHP GD est nécessaire pour convertir les images PNG ou WebP en JPEG.');
        }

        $pathname = $file->getPathname();
        $src = match ($mime) {
            'image/png' => @imagecreatefrompng($pathname),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($pathname) : false,
            default => false,
        };

        if (false === $src) {
            throw new \InvalidArgumentException('Impossible de traiter ce fichier image.');
        }

        imagejpeg($src, $targetPath, 88);
        imagedestroy($src);
    }

    /**
     * Supprime le fichier assets/img/covers/{id}.jpg s’il existe.
     */
    public function deleteIfExists(int $bookId): void
    {
        $path = $this->projectDir.'/assets/img/covers/'.$bookId.'.jpg';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
