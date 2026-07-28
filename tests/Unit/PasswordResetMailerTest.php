<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\PasswordResetMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class PasswordResetMailerTest extends TestCase
{
    public function testItBuildsAndSendsPasswordResetEmail(): void
    {
        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (RawMessage $message) use (&$sentEmail): bool {
                $sentEmail = $message;

                return true;
            }));

        $user = (new User())
            ->setName('João & Maria')
            ->setEmail('cliente@example.com');

        $service = new PasswordResetMailer(
            $mailer,
            'no-reply@mg.example.com',
            'Link do Barbeiro',
            'https://app.example.com/',
        );
        $service->send($user, 'token_com-seguranca');

        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertSame('no-reply@mg.example.com', $sentEmail->getFrom()[0]->getAddress());
        self::assertSame('Link do Barbeiro', $sentEmail->getFrom()[0]->getName());
        self::assertSame('cliente@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertSame('Redefinição de senha — Link do Barbeiro', $sentEmail->getSubject());
        self::assertStringContainsString(
            'https://app.example.com/auth/redefinir-senha?token=token_com-seguranca',
            (string) $sentEmail->getTextBody(),
        );
        self::assertStringContainsString('João &amp; Maria', (string) $sentEmail->getHtmlBody());

        $tag = $sentEmail->getHeaders()->get('X-Tag');
        self::assertInstanceOf(TagHeader::class, $tag);
        self::assertSame('password-reset', $tag->getBodyAsString());
    }
}
