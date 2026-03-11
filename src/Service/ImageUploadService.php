<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImageUploadService
{
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private string $projectDir
    ) {}

    /**
     * Valida tamanho (máx 5 MB) e tipo, e salva o arquivo em public/uploads.
     * Retorna o path relativo: "uploads/xxxxx.jpg"
     * O crop é feito no frontend (react-easy-crop); aqui apenas armazenamos.
     *
     * @throws BadRequestHttpException
     */
    public function upload(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new BadRequestHttpException('A imagem deve ter no máximo 5 MB.');
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new BadRequestHttpException('Formato não permitido. Use JPEG, PNG ou WebP.');
        }

        $uploadDir = $this->projectDir . '/public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $file->move($uploadDir, $filename);

        return 'uploads/' . $filename;
    }
}
