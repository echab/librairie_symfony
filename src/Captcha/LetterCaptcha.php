<?php

namespace App\Captcha;

use Symfony\Contracts\Translation\TranslatorInterface;

class LetterCaptcha implements CaptchaInterface
{
    protected const INDEX_MAPPING = [
        '0'  => 'first',
        '1'  => 'second',
        '2'  => 'third',
        '3'  => 'fourth',
        '4'  => 'fifth',
        '-1' => 'last',
    ];
    
    public function __construct(
        protected DictionnaryService $dictionnary,
        protected TranslatorInterface $translator
    ) {}
    
    public function getChallenge(): array
    {
        $letterIndex = array_rand(self::INDEX_MAPPING);
        $word = $this->dictionnary->getRandomWord();

        return [
            $this->getQuestion($word, $letterIndex),
            $this->getAnswer($word, $letterIndex),
        ];
    }
    
    protected function getQuestion(string $word, int $letterIndex): string
    {
        return $this->translator->trans('captcha_sentence', [
            'index' => $this->translator->trans(\sprintf('captcha_%s', self::INDEX_MAPPING[$letterIndex])),
            'letter' => $this->translator->trans('captcha_letter'),
            'word' => $word,
        ]);
    }
    
    protected function getAnswer(string $word, int $letterIndex): string
    {
        if (0 > $letterIndex) {
            $letterIndex = abs($letterIndex) - 1;
            $word = strrev($word);
        }

        return $word[$letterIndex];
    }

    public function checkAnswer($givenAnswer, $expectedAnswer): bool
    {
        return strtoupper($givenAnswer) === strtoupper($expectedAnswer);
    }
}