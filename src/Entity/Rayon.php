<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\Util;

class Rayon
{
    /** @var Rayon[] */ public static array $rayons;

    /** @var Rayon[] */ public static array $allRayons;

    /** @var Rayon[] */ public static array $allSousRayons;
    public static Rayon $defaut;

    /** @var string[] */ public static array $slugs;

    public string $titre;

    public ?int $parentCode;

    /** @var array<int, Rayon> */ public static array $byCode;
    /** @var array<string, Rayon> */ public static array $bySlug;

    public function __construct(
        public readonly int $code,
        public readonly string $label,
        public ?string $slug = null,
        public readonly ?array $sousRayons = null,
    ) {
        $this->slug = $slug ?? Util::slugify($label);

        $this->titre = $label;
        if ($sousRayons !== null) {
            foreach ($sousRayons as $r) {
                $r->parentCode = $this->code;
                $r->titre = $label !== $r->label ? "$label - $r->label" : $label;
            }
        }
    }

    public static function byCode(int $code, bool $principal = false): ?Rayon
    {
        $r = self::$byCode[$code] ?? null;
        return $principal && isset($r?->parentCode) ? self::$byCode[$r->parentCode] : $r;
    }
    public static function bySlug(string $slug, bool $principal = false): ?Rayon
    {
        $r = self::$bySlug[$slug] ?? null;
        return $principal && isset($r?->parentCode) ? self::$byCode[$r->parentCode] : $r;
    }
}

Rayon::$rayons = [
    10 => new Rayon(10, "Littérature", "litterature", [
        100 => new Rayon(100, "Mémoires, autobiographie", 'memoire'),
        101 => new Rayon(101, "Littérature francophone", "litterature-francophone"),
        102 => new Rayon(102, "Littérature étrangère", "litterature-etrangere"),
        103 => new Rayon(103, "Polar", "polar"),
        104 => new Rayon(104, "SF, Fantasy", "sf-fantasy"),
        105 => new Rayon(105, "Littérature illustrée", "litterature-illustree"),
        106 => new Rayon(106, "Romans historiques", "romans-historiques"),
        107 => new Rayon(107, "Romans terroir", "romans-terroir"),
        108 => new Rayon(108, "Revue", "revue"),
        109 => new Rayon(109, "Livres audio, grands caractères et VO", "livres-audio-grands-caracteres-et-vo")
    ]),
    11 => new Rayon(11, "Jeunesse", "jeunesse", [
        115 => new Rayon(115, "Premier âge", "premier-age"),
        114 => new Rayon(114, "Albums", "albums"),
        113 => new Rayon(113, "Documentaires", "documentaires"),
        112 => new Rayon(112, "Premières lectures 6+", "premieres-lectures-6"),
        111 => new Rayon(111, "Romans 9+", "romans-9"),
        110 => new Rayon(110, "Romans ados", "romans-ados"),
        116 => new Rayon(116, "BD Jeunesse", "bd-jeunesse"),
        117 => new Rayon(117, "Loisirs", "loisirs")
    ]),
    12 => new Rayon(12, "BD - Mangas", "bd-mangas", [
        120 => new Rayon(120, "BD", "bd"),
        122 => new Rayon(122, "Manga", "manga"),
        121 => new Rayon(121, "Comics", "comics"),
        123 => new Rayon(123, "Roman graphique", "graphiques")
    ]),
    13 => new Rayon(13, "Sciences", "sciences", [
        130 => new Rayon(130, "Sciences humaines et sociales", "sciences-humaines-et-sociales"),
        131 => new Rayon(131, "Sciences", "sciences"),
        132 => new Rayon(132, "Education, Pédagogie, Enfance", "education-pedagogie-enfance")
    ]),
    14 => new Rayon(14, "Pratique", "pratique", [
        140 => new Rayon(140, "Voyage, Tourisme", "voyage-tourisme"),
        141 => new Rayon(141, "Cuisine", "cuisine"),
        142 => new Rayon(142, "Jardin", "jardin"),
        143 => new Rayon(143, "Nature", "nature"),
        144 => new Rayon(144, "Animaux", "animaux"),
        145 => new Rayon(145, "Santé, Alimentation", "sante-alimentation"),
        146 => new Rayon(146, "Développement personnel, ésotérisme, occultisme", "developpement-personnel-esoterisme-occultisme"),
        147 => new Rayon(147, "Loisirs créatifs", "loisirs-creatifs"),
        148 => new Rayon(148, "Pratique", "pratique"),
        149 => new Rayon(149, "Transports, Sports", "transports-sports")
    ]),
    15 => new Rayon(15, "Humour", "humour", [
        150 => new Rayon(150, "Humour", "humour")
    ]),
    16 => new Rayon(16, "Poésie, Théâtre, Essais", "poesie-theatre-essais", [
        160 => new Rayon(160, "Poésie", "poesie"),
        161 => new Rayon(161, "Théâtre", "theatre"),
        162 => new Rayon(162, "Essais littéraires", "essais-litteraires")
    ]),
    17 => new Rayon(17, "Arts", "arts", [
        170 => new Rayon(170, "Peinture, Sculpture", "peinture-sculpture"),
        171 => new Rayon(171, "Dessin, Art graphique", "dessin-art-graphique"),
        172 => new Rayon(172, "Photographie", "photographie"),
        173 => new Rayon(173, "Architecture", "architecture"),
        174 => new Rayon(174, "Cinéma", "cinema"),
        175 => new Rayon(175, "Musique", "musique"),
        176 => new Rayon(176, "Mode", "mode"),
        177 => new Rayon(177, "Arts vivants", "arts-vivants"),
        178 => new Rayon(178, "Arts", "arts"),
        179 => new Rayon(179, "Histoire de l'art", "histoire-de-lart")
    ]),
    18 => new Rayon(18, "Scolaire", "scolaire-parascolaire", [
        180 => new Rayon(180, "Dictionnaires français, Linguistique", "dictionnaires-francais-linguistique"),
        181 => new Rayon(181, "Dictionnaires langues", "dictionnaires-langues"),
        182 => new Rayon(182, "Scolaire", "scolaire"),
        183 => new Rayon(183, "Parascolaire", "parascolaire")
    ]),
    19 => new Rayon(19, "Classiques", "classiques", [
        190 => new Rayon(190, "Lettres classiques, Contes", "lettres-classiques-contes")
    ]),
    40 => new Rayon(40, "Jeux - Jouets", "jeux", [
        400 => new Rayon(400, "Jeux/Jouets", "jeuxjouets"),
    ]),
    20 => new Rayon(20, "Autres", "autres", [
        // 200 => new Rayon( 200, "Papeterie", "papeterie"),
        // 300 => new Rayon( 300, "Carterie", "carterie"),
        500 => new Rayon(500, "CD/DVD", "cddvd")
        // 600 => new Rayon( 600, "Café", "cafe"),
        // 700 => new Rayon( 700, "Dépôt", "depot")
    ])
];

Rayon::$allSousRayons = array_merge(...array_map(fn($r) => $r->sousRayons, Rayon::$rayons));

Rayon::$allRayons = array_merge(Rayon::$rayons, Rayon::$allSousRayons);

Rayon::$slugs = array_map(
    fn($r) => $r->slug,
    Rayon::$allRayons
);

Rayon::$byCode = array_combine(
    array_map(fn($r) => $r->code, Rayon::$allRayons),
    Rayon::$allRayons,
);

Rayon::$bySlug = array_combine(
    array_map(fn($r) => $r->slug, Rayon::$allRayons),
    Rayon::$allRayons,
);

Rayon::$defaut = Rayon::$allRayons[0];