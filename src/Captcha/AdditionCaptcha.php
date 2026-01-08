<?php

namespace App\Captcha;

use Symfony\Contracts\Translation\TranslatorInterface;

class AdditionCaptcha implements CaptchaInterface
{
    public function __construct(
        protected TranslatorInterface $translator,
    ) {}
    
    public function getChallenge(): array
    {
        $first = rand(1, 8);
        $second = rand(1, 9 - $first);

        return [
            $this->getQuestion($first, $second),
            $this->getAnswer($first, $second),
        ];
    }
    
    protected function getQuestion(int $first, int $second): string
    {
        return $this->translator->trans('captcha_addition', [
            'first' => $first,
            'second' => $second,
        ]);
    }
    
    protected function getAnswer(int $first, int $second): string
    {
        return (string)($first + $second);
    }

    public function checkAnswer($givenAnswer, $expectedAnswer): bool
    {
        return $givenAnswer === $expectedAnswer;
    }
}