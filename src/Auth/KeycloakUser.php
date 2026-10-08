<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Auth;

use Bannerstop\Keycloak\Identity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A user that lives only in the session, for applications without their own
 * user table. The identifier is the Keycloak subject.
 */
final readonly class KeycloakUser implements Authenticatable, HasKeycloakRoles
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
    public function toArray(): array
    {
        return ['subject' => $this->subject, 'email' => $this->email, 'name' => $this->name, 'roles' => $this->roles];
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

    public function getKeycloakRoles(): array
    {
        return $this->roles;
    }

    public function getAuthIdentifierName(): string
    {
        return 'subject';
    }

    public function getAuthIdentifier(): string
    {
        return $this->subject;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * @param string $value
     */
    public function setRememberToken(#[\SensitiveParameter] $value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
