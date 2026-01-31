<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/clients')]
class ClientController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientRepository $clientRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_clients_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $search = $request->query->get('search');
        $favorites = $request->query->get('favorites');

        if ($search) {
            $clients = $this->clientRepository->searchByNameOrPhone($shop, $search);
        } elseif ($favorites === 'true') {
            $clients = $this->clientRepository->findFavoritesByShop($shop);
        } else {
            $clients = $this->clientRepository->findByShop($shop);
        }

        return $this->json(
            $this->serializer->normalize($clients, null, ['groups' => 'client:read'])
        );
    }

    #[Route('/top', name: 'api_clients_top', methods: ['GET'])]
    public function top(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $limit = $request->query->getInt('limit', 10);
        $clients = $this->clientRepository->findTopClientsByShop($shop, $limit);

        return $this->json(
            $this->serializer->normalize($clients, null, ['groups' => 'client:read'])
        );
    }

    #[Route('', name: 'api_clients_create', methods: ['POST'])]
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

        // Check if client with same phone exists
        if (isset($data['phone']) && $this->clientRepository->findByShopAndPhone($shop, $data['phone'])) {
            return $this->json(['error' => 'Cliente com este telefone já existe'], Response::HTTP_CONFLICT);
        }

        $client = new Client();
        $client->setShop($shop);
        $client->setName($data['name'] ?? '');
        $client->setPhone($data['phone'] ?? null);
        $client->setEmail($data['email'] ?? null);
        $client->setFavorite($data['favorite'] ?? false);

        // Validate
        $errors = $this->validator->validate($client);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($client, null, ['groups' => 'client:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_clients_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $client = $this->clientRepository->find($id);

        if (!$client || $client->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Cliente não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($client, null, ['groups' => 'client:read'])
        );
    }

    #[Route('/{id}', name: 'api_clients_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $client = $this->clientRepository->find($id);

        if (!$client || $client->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Cliente não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        // Check phone uniqueness if changing
        if (isset($data['phone']) && $data['phone'] !== $client->getPhone()) {
            $existing = $this->clientRepository->findByShopAndPhone($shop, $data['phone']);
            if ($existing && $existing->getId() !== $client->getId()) {
                return $this->json(['error' => 'Cliente com este telefone já existe'], Response::HTTP_CONFLICT);
            }
        }

        if (isset($data['name'])) {
            $client->setName($data['name']);
        }
        if (array_key_exists('phone', $data)) {
            $client->setPhone($data['phone']);
        }
        if (array_key_exists('email', $data)) {
            $client->setEmail($data['email']);
        }
        if (isset($data['favorite'])) {
            $client->setFavorite($data['favorite']);
        }

        // Validate
        $errors = $this->validator->validate($client);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($client, null, ['groups' => 'client:read'])
        );
    }

    #[Route('/{id}', name: 'api_clients_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $client = $this->clientRepository->find($id);

        if (!$client || $client->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Cliente não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($client);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
