<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Services\RedcapDestinationService;
use App\Services\SamlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OneLogin\Saml2\Error as SamlError;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SamlController
{
    public function __construct(
        private SamlService $saml,
        private RedcapDestinationService $destination,
    ) {}

    /**
     * Redirect the user to Okta to begin authentication.
     * RelayState carries the intended URL so we can land back on it after ACS.
     */
    public function login(Request $request): RedirectResponse
    {
        $intended = $request->query('intended', session('url.intended'));
        $relay = $intended ?: url(config('saml.default_redirect', '/'));

        $url = $this->saml->auth()->login($relay, [], false, false, true);

        return redirect()->away($url);
    }

    /**
     * Assertion Consumer Service — receives the SAML response from Okta.
     */
    public function acs(Request $request): SymfonyResponse
    {
        $auth = $this->saml->auth();

        try {
            $auth->processResponse();
        } catch (SamlError $e) {
            Log::error('SAML processResponse failed.', ['message' => $e->getMessage()]);
            abort(500, 'Unable to complete sign-in.');
        }

        $errors = $auth->getErrors();
        if (! empty($errors)) {
            Log::warning('SAML response contained errors.', [
                'errors' => $errors,
                'reason' => $auth->getLastErrorReason(),
            ]);
            abort(401, 'Sign-in failed. Please try again or contact IT support.');
        }

        if (! $auth->isAuthenticated()) {
            abort(401, 'Okta did not authenticate this session.');
        }

        $identity = $this->saml->extractIdentity($auth);
        $user = $this->saml->loginFromAssertion(
            email: $identity['email'],
            name: $identity['name'],
            nameId: $identity['nameId'],
            attributes: $identity['attributes'],
        );

        if ($user->role === Role::Student) {
            $scholar = $this->destination->findScholarByEmail($user->email);

            if ($scholar === null) {
                Auth::logout();
                $request->session()->invalidate();

                return response()->view('auth.records-not-found', [
                    'email' => $user->email,
                ], 404);
            }

            $recordId = (string) ($scholar['record_id'] ?? '');
            if ($recordId !== '' && $user->redcap_record_id !== $recordId) {
                $user->forceFill(['redcap_record_id' => $recordId])->save();
            }
        }

        $request->session()->regenerate();

        $target = $request->input('RelayState') ?: url(config('saml.default_redirect', '/'));

        return redirect()->to($target);
    }

    /**
     * Local + (optionally) Okta logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $idpSlo = (string) config('saml.idp.singleLogoutService.url', '');
        if ($idpSlo !== '') {
            $url = $this->saml->auth()->logout(url('/'));

            return redirect()->away($url);
        }

        return redirect()->to('/');
    }

    /**
     * SP metadata XML — hand this to your Okta admin when configuring the app.
     */
    public function metadata(): Response
    {
        $settings = $this->saml->auth(spValidationOnly: true)->getSettings();
        $validUntil = strtotime('+20 years');
        $metadata = $settings->getSPMetadata(false, $validUntil);

        return response($metadata, 200, ['Content-Type' => 'text/xml']);
    }
}
