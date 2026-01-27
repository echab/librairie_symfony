<?php

declare(strict_types=1);

namespace App\Entity;

// class LivreSerial {
//     public string|int $i;
//     public string|int $t;
//     public string|int $d;
//     public string|int $a;
//     public ?string $e;
//     public ?string $c;
//     public ?float $p;
//     public ?int $r;
//     public ?int $n;
// }

class Livre {

    public readonly int $ean; // i
    public readonly string $titre; // t
    public readonly \DateTime $parution; // d
    public readonly string $auteur; // a
    public readonly string $editeur; // e
    public readonly ?string $collection; // c
    public readonly float $prix; // p
    public readonly int $rayon; // r
    public readonly int $n; // n
    public readonly ?string $resume; // resume

    public function __construct(
        object $source,
        string $resume,
    ) {
        // TODO "2.85936.038.0" pour VENT TERRAL 9782859360380
        $this->ean = $source->ean ?? 
            (\is_int($source->i) || (\is_string($source->i) && preg_match('/^\d{13}$/', $source->i)))
            ? $source->i
            : 0;

        $this->titre = $source->titre ?? (string)($source->t ?? '');
        $this->auteur = $source->auteur ?? $source->a ?? '';
        $this->editeur = $source->editeur ?? (string)($source->e ?? '');
        $this->collection = $source->collection ?? $source->c ?? '';
        $this->prix = $source->prix ?? $source->p;
        
        $this->parution = $source->parution ?? (
            \is_int($source->d) && $source->d > 19000101 && $source->d < 20350000
            ? date_create_from_format('!Ymd', "$source->d")
            : date_create('2020-01-01')
        );

        $this->rayon = $source->rayon ?? $source->r;
        $this->n = $source->n ?? 0;
        $this->resume = $resume;
    }

}