<?php

declare(strict_types=1);

namespace App\Captcha;

class DictionnaryService
{
    /** @var string[] */ protected const DICTIONNARY = [
        'Accointance', 'Amphigouri', 'Anonchalir', 'Barbiturique', 'Belluaire', 'Binaural', 'Brouillamini', 'Cacochyme', 'Caligineux',
        'Cauteleux', 'Clopinette', 'Coquecigrue', 'Cosmogonie', 'Crapoussin', 'Damasquiner', 'Difficultueux', 'Dipsomanie', 'Dodeliner',
        'Engoulevent', 'Ergastule', 'Escarpolette', 'Essoriller', 'Falarique', 'Flavescent', 'Forlancer', 'Galimatias', 'Gnognotte',
        'Gracile', 'Halieutique', 'Harmattan', 'Hypergamie', 'Hypnagogique', 'Illuminisme', 'Immarcescible', 'Impavide', 'Incarnadin',
        'Jactance', 'Janotisme', 'Leptosome', 'Lustrine', 'Margoulin', 'Mignardise', 'Myoclonie', 'Nonobstant', 'Nitescence',
        'Obombrer', 'Objurgation', 'Odalisque', 'Palinodie', 'Panoptique', 'Parangon', 'Petrichor', 'Pusillanime', 'Ratiociner',
        'Rubigineux', 'Smaragdin', 'Soliflore', 'Sophisme', 'Stochastique', 'Thaumaturge', 'Truchement', 'Vergogne', 'Vertugadin',
        'Zinzinuler',
    ];

    /** @return string[] */
    public function getWords(): array {
        return self::DICTIONNARY;
    }
    
    public function getRandomWord(): string
    {
        $dictionnary = $this->getWords();
        return strtoupper($dictionnary[array_rand($dictionnary)]);
    }
}
