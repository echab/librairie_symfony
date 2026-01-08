<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class Message
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 400)]
    public string $nom = '';

    #[Assert\NotBlank]
    #[Assert\Email()]
    #[Assert\Length(max: 400)]
    public string $email = '';

    #[Assert\Length(max: 400)]
    public ?string $sujet = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $message = '';
}
