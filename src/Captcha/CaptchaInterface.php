<?php

declare(strict_types=1);

namespace App\Captcha;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('captcha_challenge')]
interface CaptchaInterface
{
    /** @return string[] */
    public function getChallenge(): array;

    public function checkAnswer(string $givenAnswer, string $expectedAnswer): bool;
}