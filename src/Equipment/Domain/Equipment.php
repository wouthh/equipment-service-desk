<?php

declare(strict_types=1);

namespace App\Equipment\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'equipment')]
class Equipment
{
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')] private string $id,
        #[ORM\Column(length: 32, unique: true)] private string $assetTag,
        #[ORM\Column(length: 100)] private string $name,
        #[ORM\Column(length: 16, enumType: Criticality::class)] private Criticality $criticality,
        #[ORM\Column(type: 'datetimetz_immutable')] private \DateTimeImmutable $registeredAt,
    ) {
        if (!preg_match('/^[A-Z][A-Z0-9-]{1,31}$/D', $assetTag)) {
            throw new \InvalidArgumentException('Invalid asset tag.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function criticality(): Criticality
    {
        return $this->criticality;
    }

    /** @return array{id:string,assetTag:string,name:string,criticality:string,registeredAt:string} */
    public function view(): array
    {
        return ['id' => $this->id, 'assetTag' => $this->assetTag, 'name' => $this->name, 'criticality' => $this->criticality->value, 'registeredAt' => $this->registeredAt->format(DATE_ATOM)];
    }
}
