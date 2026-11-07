<?php

declare(strict_types=1);

namespace App\RefundDesk\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Customer
{
    public function __construct(
        #[ORM\Id, ORM\Column(length: 16)]
        private string $id,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 180)]
        private string $email,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function update(string $name, string $email): void
    {
        $this->name = $name;
        $this->email = $email;
    }
}
