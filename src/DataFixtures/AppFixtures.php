<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                'email'    => 'user@mini-glpi.fr',
                'password' => 'password',
                'roles'    => ['ROLE_USER'],
            ],
            [
                'email'    => 'tech@mini-glpi.fr',
                'password' => 'password',
                'roles'    => ['ROLE_TECH'],
            ],
        ];

        foreach ($users as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles($data['roles']);
            $user->setPassword(
                $this->hasher->hashPassword($user, $data['password'])
            );
            $manager->persist($user);
        }

        $manager->flush();
    }
}
