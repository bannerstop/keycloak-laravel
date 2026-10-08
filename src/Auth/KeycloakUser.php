<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Auth;

use Bannerstop\Keycloak\Identity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A user that lives only in the session, for applications without their own
 * user table. The identifier is the Keycloak subject.
 */
/**
 * @implements Arrayable<string, mixed>
 */
final readonly class KeycloakUser implements Arrayable, Authenticatable, HasKeycloakRoles, \JsonSerializable
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private string $subject,
        private ?string $email,
        private string $name,
        private array $roles,
    ) {
    }

    /**
     * @param string[] $roles
     */
    public static function fromIdentity(Identity $identity, array $roles): self
    {
        return new self($identity->getSubject(), $identity->getEmail(), $identity->getDisplayName(), $roles);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['subject'], $data['name'], $data['roles']) || !is_string($data['subject']) || !is_array($data['roles'])) {
            return null;
        }

        return new self($data['subject'], isset($data['email']) ? (string) $data['email'] : null, (string) $data['name'], $data['roles']);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(): array
    {
        return ['subject' => $this->subject, 'email' => $this->email, 'name' => $this->name, 'roles' => $this->roles];
    }

    /**
     * What the frontend sees, e.g. Inertia's shared "auth.user" prop.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getKeycloakRoles(): array
    {
        return $this->roles;
    }

    #[\Override]
    public function getAuthIdentifierName(): string
    {
        return 'subject';
    }

    #[\Override]
    public function getAuthIdentifier(): string
    {
        return $this->subject;
    }

    #[\Override]
    public function getAuthPassword(): string
    {
        return '';
    }

    #[\Override]
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    #[\Override]
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * @param string $value
     */
    #[\Override]
    public function setRememberToken(#[\SensitiveParameter] $value): void
    {
    }

    #[\Override]
    public function getRememberTokenName(): string
    {
        return '';
    }
}
