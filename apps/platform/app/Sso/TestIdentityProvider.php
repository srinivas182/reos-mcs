<?php

declare(strict_types=1);

namespace App\Sso;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

/**
 * REOS's own test identity provider (outside production only), so single
 * sign-on can be set up, demonstrated and tested without an association's
 * real member system. It speaks both protocols with a real key pair:
 * - OpenID Connect: issuer {@see ISSUER}, client `reos-test`; discovery,
 *   keys and the token exchange are answered in-process;
 * - SAML 2.0: metadata from {@see samlMetadata()}, assertions signed with
 *   RSA-SHA256.
 * Its answers go through exactly the same checks as a real provider's.
 */
final class TestIdentityProvider
{
    public const ISSUER = 'https://test-idp.reos.invalid';

    public const SAML_ENTITY_ID = 'https://test-idp.reos.invalid/saml';

    public const CLIENT_ID = 'reos-test';

    public const CLIENT_SECRET = 'reos-test-secret';

    private const KEY_ID = 'reos-test-idp-1';

    /** @var array{key: string, certificate: string}|null */
    private ?array $pair = null;

    public function available(): bool
    {
        return ! app()->isProduction();
    }

    public function handles(string $issuer): bool
    {
        return $this->available() && rtrim($issuer, '/') === self::ISSUER;
    }

    /** @return array<string, mixed> */
    public function discovery(): array
    {
        return [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => url('sso-test-idp/authorize'),
            'token_endpoint' => self::ISSUER.'/token',
            'jwks_uri' => self::ISSUER.'/jwks',
            'response_types_supported' => ['code'],
            'id_token_signing_alg_values_supported' => ['RS256'],
        ];
    }

    /** @return array{keys: list<array<string, string>>} */
    public function jwks(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->keys()['certificate']) ?: throw new RuntimeException('Test IdP key unreadable'));
        $rsa = is_array($details) && is_array($details['rsa'] ?? null) ? $details['rsa'] : [];
        $encode = static fn (mixed $value): string => rtrim(strtr(base64_encode(is_string($value) ? $value : ''), '+/', '-_'), '=');

        return ['keys' => [[
            'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => self::KEY_ID,
            'n' => $encode($rsa['n'] ?? ''), 'e' => $encode($rsa['e'] ?? ''),
        ]]];
    }

    /**
     * A one-time code for this person, as the provider's sign-in page would
     * give after they sign in.
     *
     * @param  array<string, mixed>  $request  the authorisation request's query
     */
    public function issueCode(array $request, string $email, ?string $name): string
    {
        $code = Str::random(40);
        Cache::put('reos:sso:test-idp:code:'.$code, [
            'client_id' => $request['client_id'] ?? null,
            'redirect_uri' => $request['redirect_uri'] ?? null,
            'nonce' => $request['nonce'] ?? null,
            'challenge' => $request['code_challenge'] ?? null,
            'email' => mb_strtolower($email),
            'name' => $name,
        ], 120);

        return $code;
    }

    /**
     * The token endpoint: checks the client, the redirect URI and PKCE.
     *
     * @param  array<string, string>  $form
     * @return array<string, string>
     */
    public function token(array $form): array
    {
        $saved = Cache::pull('reos:sso:test-idp:code:'.($form['code'] ?? ''));

        if (! is_array($saved) || ! is_string($saved['email'] ?? null)
            || ($form['client_id'] ?? null) !== self::CLIENT_ID || $saved['client_id'] !== self::CLIENT_ID
            || ($form['client_secret'] ?? null) !== self::CLIENT_SECRET
            || ($form['redirect_uri'] ?? null) !== $saved['redirect_uri']
            || ! is_string($saved['challenge'])
            || rtrim(strtr(base64_encode(hash('sha256', $form['code_verifier'] ?? '', true)), '+/', '-_'), '=') !== $saved['challenge']) {
            return ['error' => 'invalid_grant'];
        }

        return ['token_type' => 'Bearer', 'id_token' => $this->idToken([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => self::subject($saved['email']),
            'email' => $saved['email'],
            'email_verified' => true,
            'name' => $saved['name'],
            'nonce' => $saved['nonce'],
            'iat' => time(),
            'exp' => time() + 300,
        ])];
    }

    /** @param  array<string, mixed>  $claims */
    public function idToken(array $claims, ?string $privateKey = null): string
    {
        return JWT::encode($claims, $privateKey ?? $this->keys()['key'], 'RS256', self::KEY_ID);
    }

    public function samlMetadata(): string
    {
        $certificate = SamlClient::normaliseCertificate($this->keys()['certificate']);
        $sso = htmlspecialchars(url('sso-test-idp/saml'), ENT_XML1);

        return <<<XML
            <?xml version="1.0"?>
            <md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="{$this->entity()}">
              <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
                <md:KeyDescriptor use="signing">
                  <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:X509Data><ds:X509Certificate>{$certificate}</ds:X509Certificate></ds:X509Data></ds:KeyInfo>
                </md:KeyDescriptor>
                <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
                <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="{$sso}"/>
              </md:IDPSSODescriptor>
            </md:EntityDescriptor>
            XML;
    }

    /**
     * The request REOS sent: its ID, where to answer and who asked.
     *
     * @return array{id: string, acs: string, issuer: string}|null
     */
    public function readRequest(string $samlRequest): ?array
    {
        $xml = @gzinflate((string) base64_decode($samlRequest, true));

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $dom = Utils::loadXML(new \DOMDocument, $xml);

        if (! $dom instanceof \DOMDocument || $dom->documentElement === null) {
            return null;
        }

        $issuer = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)?->textContent;
        $root = $dom->documentElement;

        return ['id' => $root->getAttribute('ID'), 'acs' => $root->getAttribute('AssertionConsumerServiceURL'), 'issuer' => (string) $issuer];
    }

    /**
     * A signed SAML response, base64-encoded as the POST binding sends it.
     * Tests pass their own key and certificate to sign with the wrong one.
     */
    public function samlResponse(string $acs, string $audience, ?string $inResponseTo, string $email, ?string $name, ?string $key = null, ?string $certificate = null, bool $sign = true, ?string $assertionId = null): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $soon = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
        $before = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $assertionId ??= '_'.Str::random(40);
        $responseId = '_'.Str::random(40);
        $inResponse = $inResponseTo === null ? '' : ' InResponseTo="'.htmlspecialchars($inResponseTo, ENT_XML1).'"';
        $esc = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_XML1);
        $subject = self::subject($email);

        $assertion = <<<XML
            <saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ID="{$assertionId}" Version="2.0" IssueInstant="{$now}"><saml:Issuer>{$this->entity()}</saml:Issuer><saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">{$subject}</saml:NameID><saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData NotOnOrAfter="{$soon}" Recipient="{$esc($acs)}"{$inResponse}/></saml:SubjectConfirmation></saml:Subject><saml:Conditions NotBefore="{$before}" NotOnOrAfter="{$soon}"><saml:AudienceRestriction><saml:Audience>{$esc($audience)}</saml:Audience></saml:AudienceRestriction></saml:Conditions><saml:AuthnStatement AuthnInstant="{$now}" SessionIndex="{$assertionId}"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement><saml:AttributeStatement><saml:Attribute Name="email"><saml:AttributeValue xsi:type="xs:string">{$esc($email)}</saml:AttributeValue></saml:Attribute><saml:Attribute Name="displayName"><saml:AttributeValue xsi:type="xs:string">{$esc($name)}</saml:AttributeValue></saml:Attribute></saml:AttributeStatement></saml:Assertion>
            XML;

        if ($sign) {
            $assertion = $this->sign($assertion, $key ?? $this->keys()['key'], $certificate ?? $this->keys()['certificate']);
        }

        $response = <<<XML
            <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="{$responseId}" Version="2.0" IssueInstant="{$now}" Destination="{$esc($acs)}"{$inResponse}><saml:Issuer>{$this->entity()}</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>{$assertion}</samlp:Response>
            XML;

        return base64_encode($response);
    }

    /** The provider's stable id for a person: not their email, as real directories do. */
    public static function subject(string $email): string
    {
        return 'test-'.substr(hash('sha256', mb_strtolower($email)), 0, 24);
    }

    /**
     * A throwaway key pair and certificate, for signing with the wrong key in tests.
     *
     * @return array{key: string, certificate: string}
     */
    public static function newKeyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]) ?: throw new RuntimeException('No key');
        $csr = openssl_csr_new(['commonName' => 'REOS test identity provider'], $key, ['digest_alg' => 'sha256']);
        $x509 = $csr === false || $csr === true ? false : openssl_csr_sign($csr, null, $key, 3650, ['digest_alg' => 'sha256']);

        if ($x509 === false || ! openssl_pkey_export($key, $pem) || ! openssl_x509_export($x509, $certificate)) {
            throw new RuntimeException('Could not make a test certificate');
        }

        return ['key' => (string) $pem, 'certificate' => (string) $certificate];
    }

    /** An enveloped RSA-SHA256 signature, placed right after the Issuer as the SAML schema requires. */
    private function sign(string $xml, string $key, string $certificate): string
    {
        $dom = new \DOMDocument;
        $dom->loadXML($xml);
        $root = $dom->documentElement ?? throw new RuntimeException('Empty assertion');
        $signature = new XMLSecurityDSig;
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference($root, XMLSecurityDSig::SHA256, ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N], ['id_name' => 'ID', 'overwrite' => false]);
        $private = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $private->loadKey($key);
        $signature->sign($private);
        $signature->add509Cert($certificate);
        $signature->insertSignature($root, $root->firstChild?->nextSibling);

        return (string) $dom->saveXML($root);
    }

    private function entity(): string
    {
        return self::SAML_ENTITY_ID;
    }

    /** @return array{key: string, certificate: string} */
    private function keys(): array
    {
        if (! $this->available()) {
            throw new RuntimeException('The test identity provider is not available in production');
        }

        if ($this->pair !== null) {
            return $this->pair;
        }

        $directory = storage_path('app/private/sso-test-idp');
        $file = $directory.'/keys.json';

        if (is_file($file)) {
            $saved = json_decode((string) file_get_contents($file), true);

            if (is_array($saved) && is_string($saved['key'] ?? null) && is_string($saved['certificate'] ?? null)) {
                return $this->pair = ['key' => $saved['key'], 'certificate' => $saved['certificate']];
            }
        }

        $pair = self::newKeyPair();
        @mkdir($directory, 0700, true);
        // Written whole, then renamed, so parallel tests never read half a file.
        $temporary = $file.'.'.Str::random(8);
        file_put_contents($temporary, (string) json_encode($pair));
        rename($temporary, $file);

        return $this->pair = $pair;
    }
}
