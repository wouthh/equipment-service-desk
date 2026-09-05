<?php

declare(strict_types=1);

namespace App\Access\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class Principal implements UserInterface
{
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')] private string $id,
        #[ORM\Column(length: 48, unique: true)] private string $handle,
        #[ORM\Column(length: 80)] private string $label,
        #[ORM\Column(length: 20, enumType: Role::class)] private Role $role,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function handle(): string
    {
        return $this->handle;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function disable(): void
    {
        $this->active = false;
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->id) {
            throw new \LogicException('Principal identifier required.');
        }

        return $this->id;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_'.strtoupper($this->role->value)];
    }

    public function eraseCredentials(): void
    {
    }

    /** @return array{id:string,handle:string,label:string,role:string} */
    public function view(): array
    {
        return ['id' => $this->id, 'handle' => $this->handle, 'label' => $this->label, 'role' => $this->role->value];
    }
}
