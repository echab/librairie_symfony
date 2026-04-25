<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Rayon;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

// TODO with symfony 7.3, lazy load by removing AbstractExtension, getFilters and getFunctions

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('highlight', [$this, 'highlight']),
            new TwigFilter('euro', [$this, 'euro']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rayons', [$this, 'rayons']),
            new TwigFunction('instanceof', [$this, 'isInstanceof']),
        ];
    }

    /**
     * Highlight search terms
     * @param string[] $searchTerms
     */
    #[AsTwigFilter('highlight')]
    public function highlight(string $text, array $searchTerms): string
    {
        if (\count($searchTerms) === 0) {
            return $text;
        }

        return preg_replace(
            '/(' . implode('|', array_map('preg_quote', $searchTerms)) . ')/i',
            '<mark>$1</mark>',
            $text
        );
    }

    /**
     * Format a number as a price in euros.
     */
    #[AsTwigFilter('euro')]
    public function euro(float $prix): string
    {
        return str_replace(',00', '', number_format($prix, 2, ',')) . '€';
    }

    /**
     * @return Rayon[]
     */
    #[AsTwigFunction('rayons')]
    public function rayons()
    {
        return Rayon::$rayons;
    }

    /**
     * @return bool
     */
    public function isInstanceof($var, $instance) {
        return \get_class($var) === $instance;
        // $reflexionClass = new \ReflectionClass($instance);
        // return $reflexionClass->isInstance($var);
        // return $var instanceof $instance;
    }
}
