<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\CatalogFixtures;
use App\Entity\User;
use App\Kernel;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final class TestDatabaseInitializer
{
    private static bool $initialized = false;

    public const ADMIN_EMAIL = 'admin_test@mylib.local';

    public const ADMIN_PASSWORD = 'AdminTest1!a';

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $kernel = new Kernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $loader = new Loader();
        $loader->addFixture(new CatalogFixtures());
        $executor = new ORMExecutor($em, new ORMPurger($em));
        $executor->execute($loader->getFixtures());

        $admin = (new User())
            ->setEmail(self::ADMIN_EMAIL)
            ->setFirstname('Admin')
            ->setLastname('Test')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT));
        $em->persist($admin);
        $em->flush();

        $kernel->shutdown();
    }
}
