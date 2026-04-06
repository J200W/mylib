<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;

final class UserPasswordHasherTest extends TestCase
{
    public function testHasherHashesAndValidatesPassword(): void
    {
        $factory = new PasswordHasherFactory([
            User::class => ['algorithm' => 'auto'],
        ]);
        $hasher = new UserPasswordHasher($factory);

        $user = (new User())
            ->setEmail('unit-test@mylib.local')
            ->setFirstname('U')
            ->setLastname('Test')
            ->setRoles(['ROLE_USER']);

        $plain = 'MonMotDePasseUnit9!';
        $user->setPassword($hasher->hashPassword($user, $plain));

        $this->assertNotSame($plain, (string) $user->getPassword());
        $this->assertTrue($hasher->isPasswordValid($user, $plain));
        $this->assertFalse($hasher->isPasswordValid($user, 'mauvais'));
    }
}
