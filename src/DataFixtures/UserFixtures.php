<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * 20 comptes : admin, bibliothécaire, et 18 usagers (user + user1…user17).
 */
final class UserFixtures extends Fixture
{
    public const REF_USER_PREFIX = 'user.';

    private const HASHED_PASSWORD = '$2y$13$vqLLNyM.ExZOf0vEzFjPLezmXmB2q3Geu537ciFEhL8nWYB6VSjTq';

    public function load(ObjectManager $manager): void
    {
        $defs = [
            'admin' => [
                'email' => 'admin@gmail.com',
                'firstname' => 'MyLib',
                'lastname' => 'Admin',
                'roles' => ['ROLE_ADMIN'],
            ],
            'librarian' => [
                'email' => 'librarian@gmail.com',
                'firstname' => 'MyLib',
                'lastname' => 'Librarian',
                'roles' => ['ROLE_LIBRARIAN'],
            ],
            'user' => [
                'email' => 'user@gmail.com',
                'firstname' => 'MyLib',
                'lastname' => 'User',
                'roles' => ['ROLE_USER'],
            ],
            'user1' => ['email' => 'user1@gmail.com', 'firstname' => 'Sophie', 'lastname' => 'Martin', 'roles' => ['ROLE_USER']],
            'user2' => ['email' => 'user2@gmail.com', 'firstname' => 'Lucas', 'lastname' => 'Bernard', 'roles' => ['ROLE_USER']],
            'user3' => ['email' => 'user3@gmail.com', 'firstname' => 'Emma', 'lastname' => 'Petit', 'roles' => ['ROLE_USER']],
            'user4' => ['email' => 'user4@gmail.com', 'firstname' => 'Thomas', 'lastname' => 'Roux', 'roles' => ['ROLE_USER']],
            'user5' => ['email' => 'user5@gmail.com', 'firstname' => 'Léa', 'lastname' => 'Dubois', 'roles' => ['ROLE_USER']],
            'user6' => ['email' => 'user6@gmail.com', 'firstname' => 'Julien', 'lastname' => 'Moreau', 'roles' => ['ROLE_USER']],
            'user7' => ['email' => 'user7@gmail.com', 'firstname' => 'Chloé', 'lastname' => 'Laurent', 'roles' => ['ROLE_USER']],
            'user8' => ['email' => 'user8@gmail.com', 'firstname' => 'Antoine', 'lastname' => 'Simon', 'roles' => ['ROLE_USER']],
            'user9' => ['email' => 'user9@gmail.com', 'firstname' => 'Manon', 'lastname' => 'Michel', 'roles' => ['ROLE_USER']],
            'user10' => ['email' => 'user10@gmail.com', 'firstname' => 'Hugo', 'lastname' => 'Lefebvre', 'roles' => ['ROLE_USER']],
            'user11' => ['email' => 'user11@gmail.com', 'firstname' => 'Inès', 'lastname' => 'Garcia', 'roles' => ['ROLE_USER']],
            'user12' => ['email' => 'user12@gmail.com', 'firstname' => 'Nathan', 'lastname' => 'André', 'roles' => ['ROLE_USER']],
            'user13' => ['email' => 'user13@gmail.com', 'firstname' => 'Camille', 'lastname' => 'Leroy', 'roles' => ['ROLE_USER']],
            'user14' => ['email' => 'user14@gmail.com', 'firstname' => 'Maxime', 'lastname' => 'Fontaine', 'roles' => ['ROLE_USER']],
            'user15' => ['email' => 'user15@gmail.com', 'firstname' => 'Clara', 'lastname' => 'Bonnet', 'roles' => ['ROLE_USER']],
            'user16' => ['email' => 'user16@gmail.com', 'firstname' => 'Paul', 'lastname' => 'Mercier', 'roles' => ['ROLE_USER']],
            'user17' => ['email' => 'user17@gmail.com', 'firstname' => 'Zoé', 'lastname' => 'Vincent', 'roles' => ['ROLE_USER']],
        ];

        foreach ($defs as $key => $u) {
            $entity = (new User())
                ->setEmail($u['email'])
                ->setPassword($this->HASHED_PASSWORD)
                ->setFirstname($u['firstname'])
                ->setLastname($u['lastname'])
                ->setRoles($u['roles']);
            $manager->persist($entity);
            $this->addReference($this->REF_USER_PREFIX.$key, $entity);
        }

        $manager->flush();
    }
}
