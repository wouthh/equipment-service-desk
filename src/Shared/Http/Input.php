<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class Input
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    public function read(Request $request, string $type): object
    {
        if ('json' !== $request->getContentTypeFormat()) {
            throw new Problem(415, 'json_required');
        }
        try {
            $decoded = json_decode($request->getContent(), false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Problem(400, 'malformed_json');
        }
        if (!$decoded instanceof \stdClass) {
            throw new Problem(400, 'object_required');
        }
        $data = get_object_vars($decoded);
        $constructor = (new \ReflectionClass($type))->getConstructor();
        if (null === $constructor) {
            throw new \LogicException('Input constructor required.');
        }
        $keys = array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $constructor->getParameters());
        foreach ($data as $key => $value) {
            if (!in_array($key, $keys, true)) {
                throw new Problem(422, 'unknown_field');
            }
            if (!is_string($value)) {
                throw new Problem(422, 'invalid_field_type');
            }
        }
        $dto = new $type(...$data);
        $violations = [];
        foreach ($this->validator->validate($dto) as $violation) {
            $violations[] = ['field' => $violation->getPropertyPath(), 'code' => 'invalid'];
        }
        if ([] !== $violations) {
            throw new Problem(422, 'validation_failed', $violations);
        }

        return $dto;
    }

    public function etag(Request $request): string
    {
        $value = $request->headers->get('If-Match');
        if (null === $value) {
            throw new Problem(428, 'version_required');
        }
        if (!preg_match('/^"[0-9a-f-]{36}:[1-9][0-9]*"$/D', $value)) {
            throw new Problem(400, 'invalid_etag');
        }

        return $value;
    }

    /** @return array<string,string> */
    public function payload(object $dto): array
    {
        $result = [];
        foreach (get_object_vars($dto) as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new \LogicException('String input DTO required.');
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /** @return array{page:int,limit:int} */
    public function pagination(Request $request): array
    {
        $page = filter_var($request->query->get('page', '1'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $limit = filter_var($request->query->get('limit', '25'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if (false === $page || false === $limit) {
            throw new Problem(422, 'invalid_pagination');
        }

        return ['page' => $page, 'limit' => $limit];
    }
}
