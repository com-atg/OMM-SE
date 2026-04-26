<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use OneLogin\Saml2\Auth as OneLoginAuth;
use RuntimeException;

class SamlService
{
    public function auth(bool $spValidationOnly = false): OneLoginAuth
    {
        return new OneLoginAuth($this->settings(), $spValidationOnly);
    }

    /**
     * Resolve (create or update) the User from the SAML assertion and log them in.
     * Returns the authenticated User.
     *
     * @param  array<string,array<int,string>>  $attributes
     */
    public function loginFromAssertion(string $email, ?string $name, ?string $nameId, array $attributes = []): User
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            throw new RuntimeException('SAML assertion did not include an email address.');
        }

        $role = $this->resolveRole($email);
        $existingUser = User::where('email', $email)->first();

        if ($role === Role::Student && $existingUser?->role === Role::Faculty) {
            $role = Role::Faculty;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name !== null && trim($name) !== '' ? trim($name) : $email,
                'role' => $role,
                'okta_nameid' => $nameId,
                'last_login_at' => now(),
            ],
        );

        Auth::login($user, remember: false);

        return $user;
    }

    /**
     * Pick a role for an email based on the env allowlists.
     * Service/Admin are allowlisted by email; anyone else defaults to Student.
     */
    public function resolveRole(string $email): Role
    {
        $email = strtolower(trim($email));

        if (in_array($email, config('saml.service_users', []), true)) {
            return Role::Service;
        }

        if (in_array($email, config('saml.admin_users', []), true)) {
            return Role::Admin;
        }

        return Role::Student;
    }

    /**
     * Extract email/name from the OneLogin SAML Auth instance after processResponse().
     *
     * @return array{email:string,name:?string,nameId:?string,attributes:array<string,array<int,string>>}
     */
    public function extractIdentity(OneLoginAuth $auth): array
    {
        $attributes = $auth->getAttributes();
        $emailAttr = (string) config('saml.attributes.email', 'email');
        $nameAttr = (string) config('saml.attributes.name', 'displayName');

        $email = $this->firstAttribute($attributes, $emailAttr) ?? $auth->getNameId();
        $name = $this->firstAttribute($attributes, $nameAttr);

        return [
            'email' => (string) $email,
            'name' => $name,
            'nameId' => $auth->getNameId() ?: null,
            'attributes' => $attributes,
        ];
    }

    public function safeRedirectTarget(?string $target): string
    {
        $fallback = route('dashboard', absolute: false);
        $target = trim((string) $target);

        if ($target === '' || preg_match('/[\x00-\x1F\x7F]/', $target)) {
            return $fallback;
        }

        if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            return $target;
        }

        $targetParts = parse_url($target);
        $appParts = parse_url(URL::to('/'));

        if (
            is_array($targetParts)
            && is_array($appParts)
            && isset($targetParts['scheme'], $targetParts['host'], $appParts['scheme'], $appParts['host'])
            && strtolower($targetParts['scheme']) === strtolower($appParts['scheme'])
            && strtolower($targetParts['host']) === strtolower($appParts['host'])
            && (int) ($targetParts['port'] ?? $this->defaultPort($targetParts['scheme'])) === (int) ($appParts['port'] ?? $this->defaultPort($appParts['scheme']))
        ) {
            return $target;
        }

        return $fallback;
    }

    /**
     * Build the OneLogin settings array from config/saml.php.
     *
     * @return array<string,mixed>
     */
    private function settings(): array
    {
        return [
            'strict' => (bool) config('saml.strict', true),
            'debug' => (bool) config('saml.debug', false),
            'baseurl' => rtrim((string) config('app.url'), '/'),
            'sp' => config('saml.sp'),
            'idp' => config('saml.idp'),
            'security' => config('saml.security'),
        ];
    }

    /**
     * @param  array<string,array<int,string>>  $attributes
     */
    private function firstAttribute(array $attributes, string $name): ?string
    {
        if (! isset($attributes[$name]) || ! is_array($attributes[$name])) {
            return null;
        }

        $value = $attributes[$name][0] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function defaultPort(string $scheme): int
    {
        return strtolower($scheme) === 'https' ? 443 : 80;
    }
}
