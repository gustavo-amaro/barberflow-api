<?php

namespace App\Controller\Api;

use App\Entity\Barber;
use App\Entity\User;
use App\Repository\BarberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/barbers')]
class BarberController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BarberRepository $barberRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_barbers_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $barbers = $this->barberRepository->findByShop($shop);

        return $this->json(
            $this->serializer->normalize($barbers, null, ['groups' => 'barber:read'])
        );
    }

    #[Route('', name: 'api_barbers_create', methods: ['POST'])]
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

        $barber = new Barber();
        $barber->setShop($shop);
        $barber->setName($data['name'] ?? '');
        $barber->setAvatar($data['avatar'] ?? null);
        $barber->setRole($data['role'] ?? 'barber');
        $barber->setPhone($data['phone'] ?? null);
        $barber->setEmail($data['email'] ?? null);
        $barber->setSpecialty($data['specialty'] ?? null);
        $barber->setCommission($data['commission'] ?? null);
        $barber->setRating($data['rating'] ?? null);
        $barber->setColor($data['color'] ?? null);
        $barber->setActive($data['active'] ?? true);

        if (isset($data['workStart'])) {
            $barber->setWorkStart(new \DateTime($data['workStart']));
        }
        if (isset($data['workEnd'])) {
            $barber->setWorkEnd(new \DateTime($data['workEnd']));
        }

        // Validate
        $errors = $this->validator->validate($barber);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($barber);
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($barber, null, ['groups' => 'barber:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_barbers_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $barber = $this->barberRepository->find($id);

        if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($barber, null, ['groups' => 'barber:read'])
        );
    }

    #[Route('/{id}', name: 'api_barbers_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $barber = $this->barberRepository->find($id);

        if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $barber->setName($data['name']);
        }
        if (array_key_exists('avatar', $data)) {
            $barber->setAvatar($data['avatar']);
        }
        if (isset($data['role'])) {
            $barber->setRole($data['role']);
        }
        if (array_key_exists('phone', $data)) {
            $barber->setPhone($data['phone']);
        }
        if (array_key_exists('email', $data)) {
            $barber->setEmail($data['email']);
        }
        if (array_key_exists('specialty', $data)) {
            $barber->setSpecialty($data['specialty']);
        }
        if (array_key_exists('commission', $data)) {
            $barber->setCommission($data['commission']);
        }
        if (array_key_exists('rating', $data)) {
            $barber->setRating($data['rating']);
        }
        if (array_key_exists('color', $data)) {
            $barber->setColor($data['color']);
        }
        if (isset($data['active'])) {
            $barber->setActive($data['active']);
        }
        if (isset($data['workStart'])) {
            $barber->setWorkStart(new \DateTime($data['workStart']));
        }
        if (isset($data['workEnd'])) {
            $barber->setWorkEnd(new \DateTime($data['workEnd']));
        }

        // Validate
        $errors = $this->validator->validate($barber);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($barber, null, ['groups' => 'barber:read'])
        );
    }

    #[Route('/{id}', name: 'api_barbers_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $barber = $this->barberRepository->find($id);

        if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($barber);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
