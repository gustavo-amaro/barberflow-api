<?php

namespace App\Controller\Api;

use App\Entity\Shop;
use App\Entity\User;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/shops')]
class ShopController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShopRepository $shopRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_shop_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check if user already has a shop
        if ($user->getShop()) {
            return $this->json(['error' => 'Usuário já possui uma barbearia'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        // Check if slug already exists
        if ($this->shopRepository->findBySlug($data['slug'] ?? '')) {
            return $this->json(['error' => 'Slug já está em uso'], Response::HTTP_CONFLICT);
        }

        $shop = new Shop();
        $shop->setName($data['name'] ?? '');
        $shop->setSlug($data['slug'] ?? '');
        $shop->setLogo($data['logo'] ?? null);
        $shop->setPhone($data['phone'] ?? null);
        $shop->setInstagram($data['instagram'] ?? null);
        $shop->setOwner($user);

        // Validate
        $errors = $this->validator->validate($shop);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($shop);
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($shop, null, ['groups' => 'shop:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('', name: 'api_shop_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($shop, null, ['groups' => 'shop:read'])
        );
    }

    #[Route('', name: 'api_shop_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        // Check slug uniqueness if changing
        if (isset($data['slug']) && $data['slug'] !== $shop->getSlug()) {
            if ($this->shopRepository->findBySlug($data['slug'])) {
                return $this->json(['error' => 'Slug já está em uso'], Response::HTTP_CONFLICT);
            }
            $shop->setSlug($data['slug']);
        }

        if (isset($data['name'])) {
            $shop->setName($data['name']);
        }
        if (array_key_exists('logo', $data)) {
            $shop->setLogo($data['logo']);
        }
        if (array_key_exists('phone', $data)) {
            $shop->setPhone($data['phone']);
        }
        if (array_key_exists('instagram', $data)) {
            $shop->setInstagram($data['instagram']);
        }

        // Validate
        $errors = $this->validator->validate($shop);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($shop, null, ['groups' => 'shop:read'])
        );
    }

    #[Route('/public/{slug}', name: 'api_shop_public', methods: ['GET'])]
    public function publicShow(string $slug): JsonResponse
    {
        $shop = $this->shopRepository->findBySlug($slug);

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'slug' => $shop->getSlug(),
            'logo' => $shop->getLogo(),
            'phone' => $shop->getPhone(),
            'instagram' => $shop->getInstagram(),
        ]);
    }
}
