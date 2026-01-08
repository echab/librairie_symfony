<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\Util;
use League\CommonMark\Output\RenderedContentInterface;
use Symfony\Component\Validator\Constraints as Assert;

class Post
{
    public string $slug;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $titre;

    public string $libraire;

    public \DateTime $date;
    public ?\DateTime $expire;

    #[Assert\Length(exactly: 13)]
    public ?int $ean;
    public ?string $auteur;
    public ?string $editeur;
    public ?\DateTime $parution;
    public ?float $prix;
    public ?int $rayonCode;

    public string $markdown;
    protected ?RenderedContentInterface $html;

    public function __construct(
        public string $category,
    ) {
    }

    public function getHtml() {
        return $this->html ?? ($this->html = Util::$markdownParser->convert($this->markdown));
    }
}
