<?php

namespace App\Service\CRange;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Accès en lecture seule au dossier Drive "Orégon sauve graph/" (exports
 * cumulatifs de la station CRange WMR300) via un compte de service Google
 * (JSON dans datas/, jamais commité — voir .gitignore). Authentification par
 * assertion JWT signée en RS256 à la main : pas de dépendance google/apiclient,
 * symfony/http-client (déjà utilisé ailleurs) suffit.
 */
class CRangeDriveClient
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    private ?string $accessToken = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $credentialsFile,
        private string $folderId,
    ) {
    }

    /** @return array<int,array{id:string,name:string,modifiedAt:\DateTimeImmutable,size:int}> */
    public function listFiles(): array
    {
        $response = $this->httpClient->request('GET', 'https://www.googleapis.com/drive/v3/files', [
            'auth_bearer' => $this->getAccessToken(),
            'query' => [
                'q' => sprintf("'%s' in parents and trashed=false", $this->folderId),
                'fields' => 'files(id,name,modifiedTime,size)',
                'pageSize' => 100,
            ],
        ]);

        $paris = new \DateTimeZone('Europe/Paris');
        $files = [];
        foreach ($response->toArray()['files'] ?? [] as $file) {
            $files[] = [
                'id' => $file['id'],
                'name' => $file['name'],
                'modifiedAt' => (new \DateTimeImmutable($file['modifiedTime']))->setTimezone($paris),
                'size' => (int) ($file['size'] ?? 0),
            ];
        }

        return $files;
    }

    public function downloadFile(string $fileId, string $destPath): void
    {
        $response = $this->httpClient->request('GET', "https://www.googleapis.com/drive/v3/files/{$fileId}", [
            'auth_bearer' => $this->getAccessToken(),
            'query' => ['alt' => 'media'],
        ]);

        file_put_contents($destPath, $response->getContent());
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        if (!is_file($this->credentialsFile)) {
            throw new \RuntimeException("Fichier de credentials Google introuvable : {$this->credentialsFile}");
        }
        $credentials = json_decode(file_get_contents($this->credentialsFile), true);

        $now = time();
        $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = self::base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URI,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "$header.$claim";
        openssl_sign($signingInput, $signature, $credentials['private_key'], 'sha256WithRSAEncryption');
        $jwt = $signingInput . '.' . self::base64UrlEncode($signature);

        $response = $this->httpClient->request('POST', self::TOKEN_URI, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);

        $this->accessToken = $response->toArray()['access_token'];

        return $this->accessToken;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
