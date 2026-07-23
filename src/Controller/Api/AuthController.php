<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\PasswordResetToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private MailerInterface $mailer,
        private RateLimiterFactory $authPublicLimiter
    ) {}

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        if ($limited = $this->limitPublicAuth($request)) return $limited;
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        // Check if email already exists
        if ($this->userRepository->findByEmail($data['email'] ?? '')) {
            return $this->json(['error' => 'Email já cadastrado'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setName($data['name'] ?? '');
        $user->setEmail(mb_strtolower(trim((string) ($data['email'] ?? ''))));
        $user->setAvatar($data['avatar'] ?? null);
        $user->setRoles(['ROLE_USER']);
        $user->setAccessRole(User::ACCESS_OWNER);
        $user->setActive(true);

        $plainPassword = (string) ($data['password'] ?? '');
        if (mb_strlen($plainPassword) < 10 || mb_strlen($plainPassword) > 128) {
            return $this->json(['error' => 'A senha deve ter entre 10 e 128 caracteres'], Response::HTTP_BAD_REQUEST);
        }

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $plainPassword
        );
        $user->setPassword($hashedPassword);

        // Validate
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Usuário criado com sucesso',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Não autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'accessRole' => $user->getAccessRole(),
            'mustChangePassword' => $user->mustChangePassword(),
            'barber' => $user->getBarber() ? [
                'id' => $user->getBarber()->getId(),
                'name' => $user->getBarber()->getName(),
            ] : null,
            'shop' => $user->getShop() ? [
                'id' => $user->getShop()->getId(),
                'name' => $user->getShop()->getName(),
                'slug' => $user->getShop()->getSlug(),
                'createdAt' => $user->getShop()->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'subscriptionPlan' => $user->getShop()->getSubscriptionPlan(),
                'subscriptionEndsAt' => $user->getShop()->getSubscriptionEndsAt()?->format(\DateTimeInterface::ATOM),
                'subscriptionActive' => $user->getShop()->isSubscriptionActive(),
                'asaasSubscriptionId' => $user->getShop()->getAsaasSubscriptionId(),
            ] : null,
        ]);
    }

    #[Route('/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Não autenticado'], Response::HTTP_UNAUTHORIZED);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $current = (string) ($data['currentPassword'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (!$this->passwordHasher->isPasswordValid($user, $current)) {
            return $this->json(['error' => 'Senha atual inválida'], Response::HTTP_BAD_REQUEST);
        }
        if (mb_strlen($password) < 10 || mb_strlen($password) > 128) {
            return $this->json(['error' => 'A nova senha deve ter entre 10 e 128 caracteres'], Response::HTTP_BAD_REQUEST);
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setMustChangePassword(false);
        $user->revokeSessions();
        $this->revokeRefreshTokens($user);
        $this->entityManager->flush();
        return $this->json(['message' => 'Senha alterada com sucesso']);
    }

    #[Route('/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        if ($limited = $this->limitPublicAuth($request)) return $limited;
        $data = json_decode($request->getContent(), true) ?? [];
        $user = $this->userRepository->findByEmail(mb_strtolower(trim((string) ($data['email'] ?? ''))));
        if ($user && $user->isActive()) {
            $this->entityManager->createQuery(
                'UPDATE App\\Entity\\PasswordResetToken token SET token.usedAt = :now WHERE token.user = :user AND token.usedAt IS NULL'
            )->setParameter('now', new \DateTimeImmutable())->setParameter('user', $user)->execute();
            $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $reset = new PasswordResetToken($user, hash('sha256', $plainToken), new \DateTimeImmutable('+30 minutes'));
            $this->entityManager->persist($reset);
            $this->entityManager->flush();
            $baseUrl = rtrim((string) ($_ENV['FRONTEND_APP_URL'] ?? $_SERVER['FRONTEND_APP_URL'] ?? 'http://localhost:3000'), '/');
            $message = (new Email())
                ->from((string) ($_ENV['MAILER_FROM'] ?? $_SERVER['MAILER_FROM'] ?? 'no-reply@linkdobarbeiro.com.br'))
                ->to((string) $user->getEmail())
                ->subject('Redefinição de senha — Link do Barbeiro')
                ->text("Use este link nos próximos 30 minutos para redefinir sua senha:\n\n{$baseUrl}/auth/redefinir-senha?token={$plainToken}");
            try { $this->mailer->send($message); } catch (\Throwable) { /* Resposta neutra evita enumeração. */ }
        }
        return $this->json(['message' => 'Se o email estiver cadastrado, enviaremos as instruções.'], Response::HTTP_ACCEPTED);
    }

    #[Route('/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        if ($limited = $this->limitPublicAuth($request)) return $limited;
        $data = json_decode($request->getContent(), true) ?? [];
        $password = (string) ($data['password'] ?? '');
        if (mb_strlen($password) < 10 || mb_strlen($password) > 128) {
            return $this->json(['error' => 'A nova senha deve ter entre 10 e 128 caracteres'], Response::HTTP_BAD_REQUEST);
        }
        $reset = $this->entityManager->getRepository(PasswordResetToken::class)->findOneBy([
            'tokenHash' => hash('sha256', (string) ($data['token'] ?? '')),
        ]);
        if (!$reset instanceof PasswordResetToken || !$reset->isValid()) {
            return $this->json(['error' => 'Link inválido ou expirado'], Response::HTTP_BAD_REQUEST);
        }
        $user = $reset->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setMustChangePassword(false);
        $user->revokeSessions();
        $reset->markUsed();
        $this->revokeRefreshTokens($user);
        $this->entityManager->flush();
        return $this->json(['message' => 'Senha redefinida com sucesso']);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user) {
            $user->revokeSessions();
            $this->revokeRefreshTokens($user);
            $this->entityManager->flush();
        }
        $response = $this->json(['message' => 'Sessão encerrada']);
        $response->headers->clearCookie('lb_access', '/api', null, false, true, 'lax');
        $response->headers->clearCookie('refresh_token', '/api', null, false, true, 'lax');
        return $response;
    }

    private function revokeRefreshTokens(User $user): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM refresh_tokens WHERE username = :email',
            ['email' => $user->getUserIdentifier()]
        );
    }

    private function limitPublicAuth(Request $request): ?JsonResponse
    {
        $limit = $this->authPublicLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if ($limit->isAccepted()) return null;
        return $this->json(['error' => 'Muitas tentativas. Aguarde um minuto e tente novamente.'], Response::HTTP_TOO_MANY_REQUESTS);
    }
}
