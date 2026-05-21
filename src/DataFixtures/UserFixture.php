<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setEmail('admin@test.com');
        $admin->setName('Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus('active');
        if (method_exists($admin, 'setVerified')) {
            $admin->setVerified(true);
        }

        // 🔐 THIS IS THE IMPORTANT PART
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin123')
        );

        $manager->persist($admin);

        $staff = new User();
        $staff->setUsername('staff');
        $staff->setEmail('staff@test.com');
        $staff->setName('Staff');
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setStatus('active');
        if (method_exists($staff, 'setVerified')) {
            $staff->setVerified(true);
        }
        $staff->setPassword(
            $this->passwordHasher->hashPassword($staff, 'staff123')
        );
        $manager->persist($staff);

        $customer = new User();
        $customer->setUsername('customer');
        $customer->setEmail('customer@test.com');
        $customer->setName('Customer');
        $customer->setRoles(['ROLE_CUSTOMER']);
        $customer->setStatus('active');
        if (method_exists($customer, 'setVerified')) {
            $customer->setVerified(true);
        }
        $customer->setPassword(
            $this->passwordHasher->hashPassword($customer, 'customer123')
        );
        $manager->persist($customer);

        $manager->flush();
    }
}
