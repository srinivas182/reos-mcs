<?php

declare(strict_types=1);

namespace App\Sso;

use App\Models\TenantSso;
use Illuminate\Support\Facades\Cache;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\IdPMetadataParser;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use Throwable;

/**
 * SAML 2.0 single sign-on with any identity provider (the associations'
 * member systems, Okta, Entra ID, ADFS, Shibboleth). REOS is the service
 * provider: it sends an AuthnRequest by redirect and takes the signed
 * response by POST.
 *
 * Checks (OneLogin php-saml in strict mode, plus REOS's own):
 * - the response or assertion is signed with one of the tenant's certificates;
 * - it answers the request this browser started (InResponseTo);
 * - destination, recipient, audience and time conditions hold;
 * - each assertion is used once (replay protection).
 */
final readonly class SamlClient
{
    /** Email attributes, most specific first. */
    private const EMAIL_ATTRIBUTES = [
        'email', 'mail', 'emailAddress', 'Email',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'urn:oid:0.9.2342.19200300.100.1.3',
    ];

    private const NAME_ATTRIBUTES = [
        'displayName', 'name', 'cn',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        'urn:oid:2.16.840.1.113730.3.1.241',
    ];

    /**
     * The identity provider's details, from its metadata XML.
     *
     * @return array{entity_id: string, sso_url: string, certificates: list<string>}
     */
    public function parseMetadata(string $xml): array
    {
        try {
            $parsed = IdPMetadataParser::parseXML($xml);
        } catch (Throwable $e) {
            throw new SsoProblem('sso.errors.metadata', 'Metadata unreadable: '.$e->getMessage());
        }

        $idp = is_array($parsed['idp'] ?? null) ? $parsed['idp'] : [];
        $sso = is_array($idp['singleSignOnService'] ?? null) ? $idp['singleSignOnService'] : [];
        $certificates = [];

        if (is_string($idp['x509cert'] ?? null)) {
            $certificates[] = $idp['x509cert'];
        }

        $multi = is_array($idp['x509certMulti'] ?? null) ? $idp['x509certMulti'] : [];

        foreach (is_array($multi['signing'] ?? null) ? $multi['signing'] : [] as $certificate) {
            if (is_string($certificate)) {
                $certificates[] = $certificate;
            }
        }

        $entityId = $idp['entityId'] ?? null;
        $url = $sso['url'] ?? null;
        $certificates = array_values(array_unique(array_map(self::normaliseCertificate(...), $certificates)));

        if (! is_string($entityId) || $entityId === '' || ! is_string($url) || $url === '' || $certificates === []) {
            throw new SsoProblem('sso.errors.metadata', 'Metadata lacks entity ID, redirect sign-in address or signing certificate');
        }

        return ['entity_id' => $entityId, 'sso_url' => $url, 'certificates' => $certificates];
    }

    /**
     * Where to send the browser, and the request ID the answer must carry.
     *
     * @return array{url: string, request_id: string}
     */
    public function loginUrl(TenantSso $sso, ServiceProvider $sp, string $relayState): array
    {
        $auth = new Auth($this->settings($sso, $sp));
        $url = (string) $auth->login($relayState, [], false, false, true);

        return ['url' => $url, 'request_id' => (string) $auth->getLastRequestID()];
    }

    public function finish(TenantSso $sso, ServiceProvider $sp, string $samlResponse, string $requestId): SsoClaims
    {
        // OneLogin compares Destination and Recipient with "the current URL",
        // which it reads from $_SERVER. Point it at the ACS address for the
        // check, then put everything back (the process may serve others).
        $server = $_SERVER;
        $acs = parse_url($sp->acsUrl);
        Utils::setBaseURL(($acs['scheme'] ?? 'https').'://'.($acs['host'] ?? '').(isset($acs['port']) ? ':'.$acs['port'] : '').'/');
        $_SERVER['REQUEST_URI'] = $acs['path'] ?? '/';
        unset($_SERVER['QUERY_STRING']);

        try {
            $response = new Response(new Settings($this->settings($sso, $sp)), $samlResponse);

            if (! $response->isValid($requestId)) {
                throw new SsoProblem('sso.errors.rejected', 'SAML response rejected: '.$response->getError());
            }

            $assertionId = (string) $response->getAssertionId();
            $notOnOrAfter = $response->getAssertionNotOnOrAfter();
            $nameId = (string) $response->getNameId();
            $attributes = $response->getAttributes();
        } catch (SsoProblem $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SsoProblem('sso.errors.rejected', 'SAML response unreadable: '.$e->getMessage());
        } finally {
            $_SERVER = $server;
            Utils::setBaseURL('');
        }

        $ttl = max(300, $notOnOrAfter - time() + 300);

        if ($assertionId === '' || ! Cache::add('reos:sso:saml-assertion:'.$sso->organisation_id.':'.sha1($assertionId), true, $ttl)) {
            throw new SsoProblem('sso.errors.replayed', 'SAML assertion already used');
        }

        if ($nameId === '') {
            throw new SsoProblem('sso.errors.rejected', 'SAML response has no NameID');
        }

        $email = $this->first($attributes, self::EMAIL_ATTRIBUTES) ?? (filter_var($nameId, FILTER_VALIDATE_EMAIL) !== false ? $nameId : null);
        $name = $this->first($attributes, self::NAME_ATTRIBUTES)
            ?? (trim(($this->first($attributes, ['givenName', 'firstName']) ?? '').' '.($this->first($attributes, ['sn', 'surname', 'lastName']) ?? '')) ?: null);

        return new SsoClaims($nameId, $email === null ? null : mb_strtolower($email), $name);
    }

    /** REOS's own metadata, for the identity provider's admin. */
    public function serviceProviderMetadata(ServiceProvider $sp): string
    {
        $settings = new Settings([
            'strict' => true,
            'sp' => $this->spSettings($sp),
            'idp' => ['entityId' => 'about:blank', 'singleSignOnService' => ['url' => 'about:blank'], 'x509cert' => ''],
        ], true);

        return $settings->getSPMetadata();
    }

    /** @return array<string, mixed> */
    private function settings(TenantSso $sso, ServiceProvider $sp): array
    {
        return [
            'strict' => true,
            'debug' => false,
            'sp' => $this->spSettings($sp),
            'idp' => [
                'entityId' => $sso->setting('entity_id'),
                'singleSignOnService' => ['url' => $sso->setting('sso_url'), 'binding' => Constants::BINDING_HTTP_REDIRECT],
                'x509certMulti' => ['signing' => $sso->certificates()],
            ],
            'security' => [
                'authnRequestsSigned' => false,
                // php-saml always demands a signature on the response or the
                // assertion; providers differ in which they sign.
                'wantMessagesSigned' => false,
                'wantAssertionsSigned' => false,
                'wantAssertionsEncrypted' => false,
                'wantNameId' => true,
                'wantXMLValidation' => true,
                'rejectUnsolicitedResponsesWithInResponseTo' => true,
                'relaxDestinationValidation' => false,
                'destinationStrictlyMatches' => true,
                'requestedAuthnContext' => false,
                'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
                'rejectDeprecatedAlgorithm' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function spSettings(ServiceProvider $sp): array
    {
        return [
            'entityId' => $sp->entityId,
            'assertionConsumerService' => ['url' => $sp->acsUrl, 'binding' => Constants::BINDING_HTTP_POST],
            'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $names
     */
    private function first(array $attributes, array $names): ?string
    {
        foreach ($names as $name) {
            $values = $attributes[$name] ?? null;
            $value = is_array($values) ? ($values[0] ?? null) : null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public static function normaliseCertificate(string $certificate): string
    {
        return (string) preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $certificate);
    }
}
