<?php

declare(strict_types=1);

namespace DemoSprint;

/** Résout les utilisateurs Jira en Person (nom affiché + rôle). Dev par défaut. */
final class UserDirectory
{
    /** @var array<string,Role> login (MAJ) => rôle */
    private array $rolesByLogin = [];
    /** @var array<int,array{0:string,1:Role}> motif de nom => rôle */
    private array $rolesByName = [];
    /** @var array<string,string> */
    private array $overrides = [];
    /** @var array<string,Person> */
    private array $byId = [];
    /** @var array<string,Person> */
    private array $seen = [];

    public function __construct(private readonly JiraClient $jira, array $rolesConfig, array $overrides)
    {
        foreach (['po' => Role::Po, 'scrum' => Role::Scrum] as $key => $role) {
            foreach ($rolesConfig[$key]['logins'] ?? [] as $login) {
                $this->rolesByLogin[strtoupper($login)] = $role;
            }
            foreach ($rolesConfig[$key]['names'] ?? [] as $name) {
                $this->rolesByName[] = ['/\b' . preg_quote($name, '/') . '\b/iu', $role];
            }
        }
        foreach ($overrides as $login => $name) {
            $this->overrides[strtoupper($login)] = $name;
        }
    }

    /** Assigné courant d'un ticket (champ fields.assignee). */
    public function fromIssueUser(?array $user): ?Person
    {
        if (!$user) {
            return null;
        }
        $login = (string) ($user['name'] ?? $user['key'] ?? '');
        return $login === '' ? null : $this->make($login, $user['displayName'] ?? null);
    }

    /**
     * Utilisateur référencé par l'historique. Selon la version de Jira, `to` est le login
     * ou la clé interne : on résout via l'API si l'identifiant n'est pas déjà connu.
     */
    public function fromChangelog(?string $id, ?string $label): ?Person
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (isset($this->byId[$id])) {
            return $this->byId[$id];
        }
        $known = isset($this->rolesByLogin[strtoupper($id)]) || isset($this->overrides[strtoupper($id)]);
        $user  = $known ? null : $this->jira->getUserByKey($id);

        return $this->byId[$id] = $this->make((string) ($user['name'] ?? $id), $user['displayName'] ?? $label);
    }

    /** @return Person[] personnes rencontrées pendant l'exécution (pour contrôle des rôles) */
    public function seen(): array
    {
        return array_values($this->seen);
    }

    private function make(string $login, ?string $displayName): Person
    {
        $name   = $this->overrides[strtoupper($login)] ?? (($displayName !== null && $displayName !== '') ? $displayName : $login);
        $person = new Person($login, $name, $this->roleFor($login, $displayName));
        return $this->seen[strtoupper($login)] = $person;
    }

    private function roleFor(string $login, ?string $displayName): Role
    {
        if (isset($this->rolesByLogin[strtoupper($login)])) {
            return $this->rolesByLogin[strtoupper($login)];
        }
        if ($displayName !== null) {
            foreach ($this->rolesByName as [$pattern, $role]) {
                if (preg_match($pattern, $displayName)) {
                    return $role;
                }
            }
        }
        return Role::Dev;
    }
}
