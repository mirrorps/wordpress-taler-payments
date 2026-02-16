<?php
namespace TalerPayments\Services\DTO;

/**
 * Strongly-typed container for SDK factory options.
 */
final class TalerFactoryOptions
{
    public function __construct(
        private string $baseUrl,
        private ?string $token = null,
        private ?string $username = null,
        private ?string $password = null,
        private ?string $instance = null,
        private ?string $scope = null,
        private ?int $durationUs = null,
        private ?string $description = null,
    ) {
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            baseUrl:    self::toRequiredString($options['base_url'] ?? ''),
            token:      self::toOptionalString($options['token'] ?? null),
            username:   self::toOptionalString($options['username'] ?? null),
            password:   self::toOptionalString($options['password'] ?? null),
            instance:   self::toOptionalString($options['instance'] ?? null),
            scope:      self::toOptionalString($options['scope'] ?? null),
            durationUs: isset($options['duration_us']) ? (int) $options['duration_us'] : null,
            description: self::toOptionalString($options['description'] ?? null),
        );
    }

    public static function withBaseUrl(string $baseUrl): self
    {
        return new self($baseUrl);
    }

    public function withToken(string $token): self
    {
        return new self(
            baseUrl: $this->baseUrl,
            token: $token,
            username: $this->username,
            password: $this->password,
            instance: $this->instance,
            scope: $this->scope,
            durationUs: $this->durationUs,
            description: $this->description,
        );
    }

    public function withUserPass(
        string $username,
        string $password,
        string $instance,
        string $scope,
        int $durationUs,
        string $description
    ): self {
        return new self(
            baseUrl: $this->baseUrl,
            token: $this->token,
            username: $username,
            password: $password,
            instance: $instance,
            scope: $scope,
            durationUs: $durationUs,
            description: $description,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $options = [
            'base_url' => $this->baseUrl,
        ];

        if ($this->token !== null) {
            $options['token'] = $this->token;
        }
        if ($this->username !== null) {
            $options['username'] = $this->username;
        }
        if ($this->password !== null) {
            $options['password'] = $this->password;
        }
        if ($this->instance !== null) {
            $options['instance'] = $this->instance;
        }
        if ($this->scope !== null) {
            $options['scope'] = $this->scope;
        }
        if ($this->durationUs !== null) {
            $options['duration_us'] = $this->durationUs;
        }
        if ($this->description !== null) {
            $options['description'] = $this->description;
        }

        return $options;
    }

    private static function toRequiredString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }

    private static function toOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return (string) $value;
    }
}
