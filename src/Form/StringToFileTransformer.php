<?php declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Transforms between a Boolean and a string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @implements DataTransformerInterface<bool, string>
 */
class StringToFileTransformer implements DataTransformerInterface
{
    /**
     * Transforms a string into a File.
     *
     * @param bool $value string value
     *
     * @throws TransformationFailedException if the given value is not a string
     */
    public function transform(mixed $value): ?File
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        return new File($value, false);
    }

    /**
     * Transforms a file into a string.
     *
     * @param string $value File value
     *
     * @throws TransformationFailedException if the given value is not a File
     */
    public function reverseTransform(mixed $value): string
    {

        if (!($value instanceof File)) {
            throw new TransformationFailedException('Expected a string.');
        }

        return $value->getPathname();
    }
}
