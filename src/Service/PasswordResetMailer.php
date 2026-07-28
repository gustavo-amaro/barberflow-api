<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $senderAddress,
        private string $senderName,
        private string $frontendUrl,
    ) {
    }

    public function send(User $user, string $plainToken): void
    {
        $resetUrl = sprintf(
            '%s/auth/redefinir-senha?token=%s',
            rtrim($this->frontendUrl, '/'),
            rawurlencode($plainToken),
        );

        $email = (new Email())
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to(new Address((string) $user->getEmail(), (string) $user->getName()))
            ->subject('Redefinição de senha — Link do Barbeiro')
            ->text(<<<TEXT
Olá, {$user->getName()}!

Recebemos uma solicitação para redefinir sua senha.

Use o link abaixo nos próximos 30 minutos:
{$resetUrl}

Se você não solicitou a redefinição, ignore este e-mail.
TEXT)
            ->html($this->htmlBody((string) $user->getName(), $resetUrl));

        $email->getHeaders()->add(new TagHeader('password-reset'));

        $this->mailer->send($email);
    }

    private function htmlBody(string $name, string $resetUrl): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<body style="margin:0;background:#f4f4f5;font-family:Arial,sans-serif;color:#27272a">
  <div style="max-width:560px;margin:0 auto;padding:32px 16px">
    <div style="background:#fff;border-radius:12px;padding:32px">
      <h1 style="font-size:22px;margin:0 0 20px">Redefinição de senha</h1>
      <p>Olá, {$safeName}!</p>
      <p>Recebemos uma solicitação para redefinir sua senha.</p>
      <p style="margin:28px 0">
        <a href="{$safeUrl}" style="background:#18181b;color:#fff;text-decoration:none;border-radius:8px;padding:12px 20px;display:inline-block">
          Redefinir minha senha
        </a>
      </p>
      <p style="font-size:14px;color:#52525b">Este link expira em 30 minutos. Se você não fez esta solicitação, ignore este e-mail.</p>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
