<?php

namespace App\Controller\Api;

use App\Service\ImageUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class UploadController extends AbstractController
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    #[Route('/upload', name: 'api_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file') ?? $request->files->get('image');
        if (!$file) {
            return $this->json(
                ['error' => 'Nenhum arquivo enviado. Use o campo "file" ou "image".'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $path = $this->imageUploadService->upload($file);
            return $this->json([
                'path' => $path,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\BadRequestHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
