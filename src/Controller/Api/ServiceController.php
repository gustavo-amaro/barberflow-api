<?php

namespace App\Controller\Api;

use App\Entity\Service;
use App\Entity\User;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/services')]
class ServiceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_services_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $category = $request->query->get('category');
        $active = $request->query->get('active');

        if ($category) {
            $services = $this->serviceRepository->findByShopAndCategory($shop, $category);
        } elseif ($active === 'true') {
            $services = $this->serviceRepository->findActiveByShop($shop);
        } else {
            $services = $this->serviceRepository->findByShop($shop);
        }

        return $this->json(
            $this->serializer->normalize($services, null, ['groups' => 'service:read'])
        );
    }

    #[Route('', name: 'api_services_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
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

        $service = new Service();
        $service->setShop($shop);
        $service->setName($data['name'] ?? '');
        $service->setDuration($data['duration'] ?? 0);
        $service->setPrice($data['price'] ?? '0.00');
        $service->setIcon($data['icon'] ?? null);
        $service->setDescription($data['description'] ?? null);
        $service->setCategory($data['category'] ?? null);
        $service->setActive($data['active'] ?? true);
        $service->setPopular($data['popular'] ?? false);

        // Validate
        $errors = $this->validator->validate($service);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($service, null, ['groups' => 'service:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_services_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $service = $this->serviceRepository->find($id);

        if (!$service || $service->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Serviço não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($service, null, ['groups' => 'service:read'])
        );
    }

    #[Route('/{id}', name: 'api_services_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $service = $this->serviceRepository->find($id);

        if (!$service || $service->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Serviço não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $service->setName($data['name']);
        }
        if (isset($data['duration'])) {
            $service->setDuration($data['duration']);
        }
        if (isset($data['price'])) {
            $service->setPrice($data['price']);
        }
        if (array_key_exists('icon', $data)) {
            $service->setIcon($data['icon']);
        }
        if (array_key_exists('description', $data)) {
            $service->setDescription($data['description']);
        }
        if (array_key_exists('category', $data)) {
            $service->setCategory($data['category']);
        }
        if (isset($data['active'])) {
            $service->setActive($data['active']);
        }
        if (isset($data['popular'])) {
            $service->setPopular($data['popular']);
        }

        // Validate
        $errors = $this->validator->validate($service);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($service, null, ['groups' => 'service:read'])
        );
    }

    #[Route('/{id}', name: 'api_services_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $service = $this->serviceRepository->find($id);

        if (!$service || $service->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Serviço não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($service);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
