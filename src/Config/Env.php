<?php

declare(strict_types=1);

namespace Noelo\Monitor\Config;

final class Env
{
    /** @var array<string,string> */
    private array $values;

    public function __construct(string $envFilePath)
    {
        if (!is_file($envFilePath)) {
            throw new \RuntimeException('Chyba konfiguracie: .env subor nebol najdeny.');
        }

        $this->values = self::parse($envFilePath);
    }

    /** @return array<string,string> */
    private static function parse(string $path): array
    {
        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Chyba konfiguracie: .env subor sa neda precitat.');
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value);
        }

        return $values;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->values[$key] ?? $default;
        return $value === '' ? $default : $value;
    }

    public function require(string $key): string
    {
        $value = $this->get($key);
        if ($value === null) {
            throw new \RuntimeException("Chyba konfiguracie: chyba povinna hodnota '{$key}' v .env.");
        }
        return $value;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key);
        return $value === null ? $default : (int) $value;
    }
}
