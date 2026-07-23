<?php

namespace App\Controller\Api;

use App\Entity\Barber;
use App\Entity\User;
use App\Repository\BarberRepository;
use App\Util\TimeInputParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/barbers')]
class BarberController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BarberRepository $barberRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher
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

        $barbers = $user->isBarberUser()
            ? ($user->getBarber() ? [$user->getBarber()] : [])
            : $this->barberRepository->findByShop($shop);

        return $this->json(array_map(fn (Barber $barber) => $this->barberData($barber, $user), $barbers));
    }

    #[Route('', name: 'api_barbers_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
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

        try {
            if (isset($data['workStart'])) {
                $barber->setWorkStart(TimeInputParser::required($data['workStart'], 'workStart'));
            }
            if (isset($data['workEnd'])) {
                $barber->setWorkEnd(TimeInputParser::required($data['workEnd'], 'workEnd'));
            }
            if (array_key_exists('lunchStart', $data)) {
                $barber->setLunchStart(TimeInputParser::optional($data['lunchStart'], 'lunchStart'));
            }
            if (array_key_exists('lunchEnd', $data)) {
                $barber->setLunchEnd(TimeInputParser::optional($data['lunchEnd'], 'lunchEnd'));
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
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

        return $this->json($this->barberData($barber, $user), Response::HTTP_CREATED);
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

        if (!$barber || $barber->getShop()->getId() !== $shop->getId()
            || ($user->isBarberUser() && $user->getBarber()?->getId() !== $barber->getId())) {
            return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->barberData($barber, $user));
    }

    #[Route('/{id}', name: 'api_barbers_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
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
        try {
            if (isset($data['workStart'])) {
                $barber->setWorkStart(TimeInputParser::required($data['workStart'], 'workStart'));
            }
            if (isset($data['workEnd'])) {
                $barber->setWorkEnd(TimeInputParser::required($data['workEnd'], 'workEnd'));
            }
            if (array_key_exists('lunchStart', $data)) {
                $barber->setLunchStart(TimeInputParser::optional($data['lunchStart'], 'lunchStart'));
            }
            if (array_key_exists('lunchEnd', $data)) {
                $barber->setLunchEnd(TimeInputParser::optional($data['lunchEnd'], 'lunchEnd'));
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
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

        return $this->json($this->barberData($barber, $user));
    }

    #[Route('/{id}', name: 'api_barbers_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
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

    #[Route('/{id}/access', name: 'api_barber_access_create', methods: ['POST'])]
    public function createAccess(int $id): JsonResponse
    {
        /** @var User $owner */
        $owner = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $barber = $this->ownedBarber($id, $owner);
        if (!$barber) return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        if ($barber->getUser()) return $this->json(['error' => 'Este profissional já possui acesso'], Response::HTTP_CONFLICT);
        $email = mb_strtolower(trim((string) $barber->getEmail()));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Cadastre um email válido para o profissional'], Response::HTTP_BAD_REQUEST);
        }
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) return $this->json(['error' => 'Este email já pertence a outra conta'], Response::HTTP_CONFLICT);

        $temporaryPassword = $this->temporaryPassword();
        $user = new User();
        $user->setName((string) $barber->getName())->setEmail($email)
            ->setAccessRole(User::ACCESS_BARBER)->setRoles(['ROLE_USER'])
            ->setShop($owner->getShop())->setBarber($barber)->setActive(true)
            ->setMustChangePassword(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $temporaryPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $this->json([
            'barber' => $this->barberData($barber, $owner),
            'temporaryPassword' => $temporaryPassword,
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/access/reset-password', name: 'api_barber_access_reset', methods: ['POST'])]
    public function resetAccessPassword(int $id): JsonResponse
    {
        /** @var User $owner */
        $owner = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $barber = $this->ownedBarber($id, $owner);
        $member = $barber?->getUser();
        if (!$barber || !$member || $member->isOwner()) {
            return $this->json(['error' => 'Acesso de profissional não encontrado'], Response::HTTP_NOT_FOUND);
        }
        $temporaryPassword = $this->temporaryPassword();
        $member->setPassword($this->passwordHasher->hashPassword($member, $temporaryPassword));
        $member->setMustChangePassword(true)->setActive(true);
        $member->revokeSessions();
        $this->revokeRefreshTokens($member);
        $this->entityManager->flush();
        return $this->json(['temporaryPassword' => $temporaryPassword]);
    }

    #[Route('/{id}/access', name: 'api_barber_access_update', methods: ['PATCH'])]
    public function updateAccess(int $id, Request $request): JsonResponse
    {
        /** @var User $owner */
        $owner = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $barber = $this->ownedBarber($id, $owner);
        $member = $barber?->getUser();
        if (!$barber || !$member || $member->isOwner()) {
            return $this->json(['error' => 'Acesso de profissional não encontrado'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $member->setActive((bool) ($data['active'] ?? false));
        if (!$member->isActive()) {
            $member->revokeSessions();
            $this->revokeRefreshTokens($member);
        }
        $this->entityManager->flush();
        return $this->json($this->barberData($barber, $owner));
    }

    #[Route('/{id}/link-owner', name: 'api_barber_link_owner', methods: ['POST'])]
    public function linkOwner(int $id): JsonResponse
    {
        /** @var User $owner */
        $owner = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $barber = $this->ownedBarber($id, $owner);
        if (!$barber) return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        if ($owner->getBarber() && $owner->getBarber()->getId() !== $barber->getId()) {
            return $this->json(['error' => 'Sua conta já está vinculada a outro perfil'], Response::HTTP_CONFLICT);
        }
        if ($barber->getUser() && $barber->getUser()->getId() !== $owner->getId()) {
            return $this->json(['error' => 'Este profissional já possui outra conta'], Response::HTTP_CONFLICT);
        }
        $owner->setBarber($barber);
        $this->entityManager->flush();
        return $this->json($this->barberData($barber, $owner));
    }

    #[Route('/{id}/link-owner', name: 'api_barber_unlink_owner', methods: ['DELETE'])]
    public function unlinkOwner(int $id): JsonResponse
    {
        /** @var User $owner */
        $owner = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $barber = $this->ownedBarber($id, $owner);
        if (!$barber || $barber->getUser()?->getId() !== $owner->getId()) {
            return $this->json(['error' => 'Este perfil não está vinculado à sua conta'], Response::HTTP_NOT_FOUND);
        }
        $barber->setUser(null);
        $owner->setBarber(null);
        $this->entityManager->flush();
        return $this->json($this->barberData($barber, $owner));
    }

    private function ownedBarber(int $id, User $user): ?Barber
    {
        $barber = $this->barberRepository->find($id);
        return $barber && $user->getShop() && $barber->getShop()->getId() === $user->getShop()->getId() ? $barber : null;
    }

    private function barberData(Barber $barber, User $viewer): array
    {
        $data = $this->serializer->normalize($barber, null, ['groups' => 'barber:read']);
        if ($viewer->isBarberUser()) unset($data['commission'], $data['rating']);
        $member = $barber->getUser();
        $data['access'] = $member ? [
            'enabled' => $member->isActive(),
            'mustChangePassword' => $member->mustChangePassword(),
            'isOwner' => $member->isOwner(),
        ] : null;
        $data['isMe'] = $member?->getId() === $viewer->getId();
        return $data;
    }

    private function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $password = '';
        for ($i = 0; $i < 20; $i++) $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        return $password;
    }

    private function revokeRefreshTokens(User $user): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM refresh_tokens WHERE username = :email',
            ['email' => $user->getUserIdentifier()]
        );
    }
}
