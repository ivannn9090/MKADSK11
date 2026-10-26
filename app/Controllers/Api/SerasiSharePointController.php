<?php
namespace App\Controllers\Api;

use App\Models\SerasiDocumentModel;
use App\Models\SerasiFolderModel;

/**
 * SerasiSharePointController
 * Integrasi Microsoft SharePoint Online via SharePoint REST API.
 *
 * ── METODE AUTH 1: Azure AD App Registration (Client Credentials) ──
 *   SHAREPOINT_AUTH_METHOD   = azure   (default)
 *   SHAREPOINT_SITE_URL      = https://contoso.sharepoint.com/sites/DSK
 *   SHAREPOINT_TENANT_ID     = <azure-tenant-id>
 *   SHAREPOINT_CLIENT_ID     = <app-client-id>@<tenant-id>  atau hanya <app-client-id>
 *   SHAREPOINT_CLIENT_SECRET = <app-client-secret>
 *
 * ── METODE AUTH 2: SharePoint App-Only (AppInv.aspx) ──
 *   Tidak perlu Azure Portal. Cukup Site Collection Admin.
 *   SHAREPOINT_AUTH_METHOD   = apponly
 *   SHAREPOINT_SITE_URL      = https://contoso.sharepoint.com/sites/DSK
 *   SHAREPOINT_CLIENT_ID     = <client-id-dari-appregnew.aspx>@<tenant-id>
 *   SHAREPOINT_CLIENT_SECRET = <client-secret-dari-appregnew.aspx>
 *
 * ── METODE AUTH 3: Username + Password (ROPC) ──
 *   Hanya untuk akun non-MFA / service account.
 *   SHAREPOINT_AUTH_METHOD   = password
 *   SHAREPOINT_SITE_URL      = https://contoso.sharepoint.com/sites/DSK
 *   SHAREPOINT_TENANT_ID     = <tenant-id>
 *   SHAREPOINT_CLIENT_ID     = <azure-client-id>  (gunakan "Microsoft Office" = d3590ed6-...)
 *   SHAREPOINT_USERNAME      = serviceaccount@contoso.onmicrosoft.com
 *   SHAREPOINT_PASSWORD      = <password>
 *
 *   SHAREPOINT_LIB_PATH      = /sites/DSK/Shared Documents/Direktorat Sistem Kearsipan
 */
class SerasiSharePointController extends BaseApiController
{
    private string $siteUrl;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $libPath;
    private string $authMethod;
    private string $spUsername;
    private string $spPassword;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->siteUrl      = rtrim(env('SHAREPOINT_SITE_URL',      ''), '/');
        $this->tenantId     = env('SHAREPOINT_TENANT_ID',     '');
        $this->clientId     = env('SHAREPOINT_CLIENT_ID',     '');
        $this->clientSecret = env('SHAREPOINT_CLIENT_SECRET', '');
        $this->authMethod   = strtolower(env('SHAREPOINT_AUTH_METHOD', 'azure'));
        $this->spUsername   = env('SHAREPOINT_USERNAME', '');
        $this->spPassword   = env('SHAREPOINT_PASSWORD', '');
        $this->libPath      = env('SHAREPOINT_LIB_PATH',
            '/sites/DSK/Shared Documents/Direktorat Sistem Kearsipan');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/sharepoint/status
    // ──────────────────────────────────────────────────────────────
    public function status()
    {
        $configured = $this->_isConfigured();

        $lastSync = null;
        if ($configured) {
            $row = \Config\Database::connect()
                ->table('serasi_folders')
                ->select('MAX(synced_at) AS last_sync')
                ->get()->getRowArray();
            $lastSync = $row['last_sync'] ?? null;
        }

        return $this->success([
            'configured'  => $configured,
            'auth_method' => $this->authMethod,
            'site_url'    => $configured ? $this->siteUrl : null,
            'lib_path'    => $this->libPath,
            'last_sync'   => $lastSync,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/serasi/sharepoint/test
    // ──────────────────────────────────────────────────────────────
    public function testConnection()
    {
        $this->requireRole(['direktur']);
        if (!$this->_isConfigured()) {
            return $this->fail(
                'Konfigurasi SharePoint belum lengkap. Tambahkan SHAREPOINT_SITE_URL, ' .
                'SHAREPOINT_TENANT_ID, SHAREPOINT_CLIENT_ID, dan SHAREPOINT_CLIENT_SECRET ke .env', 400
            );
        }

        $token = $this->_getAccessToken();
        if (!$token) return $this->fail('Gagal mendapatkan access token dari Azure AD.', 401);

        // Test: GET _api/web — ambil info site
        $res = $this->_spGet("{$this->siteUrl}/_api/web?\$select=Title,Url,ServerRelativeUrl", $token);
        if (!$res || isset($res['error'])) {
            $msg = $res['error']['message'] ?? ($res['odata.error']['message']['value'] ?? 'Koneksi gagal');
            return $this->fail("SharePoint REST API Error: {$msg}");
        }

        return $this->success([
            'site_title' => $res['Title']             ?? 'SharePoint Site',
            'site_url'   => $res['Url']               ?? $this->siteUrl,
            'site_path'  => $res['ServerRelativeUrl'] ?? '/',
        ], 'Koneksi SharePoint berhasil!');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/sharepoint/browse?path=
    // Browse folder pada server relative path
    // ──────────────────────────────────────────────────────────────
    public function browse()
    {
        $this->requireRole(['direktur','ketua_tim']);
        $path  = $this->request->getGet('path') ?: $this->libPath;
        $token = $this->_getAccessToken();
        if (!$token) return $this->fail('Gagal autentikasi ke SharePoint.', 401);

        $enc = rawurlencode("'{$path}'");
        $url = "{$this->siteUrl}/_api/web/GetFolderByServerRelativeUrl({$enc})/Folders"
             . "?\$select=Name,ServerRelativeUrl,ItemCount,UniqueId&\$orderby=Name";

        $res = $this->_spGet($url, $token);
        if (!$res || isset($res['error']) || isset($res['odata.error'])) {
            return $this->fail('Gagal membaca folder SharePoint. Pastikan path dan konfigurasi benar.');
        }

        $items = array_map(fn($f) => [
            'name'       => $f['Name'],
            'path'       => $f['ServerRelativeUrl'],
            'item_count' => $f['ItemCount'] ?? 0,
            'unique_id'  => $f['UniqueId']  ?? null,
        ], $res['value'] ?? []);

        return $this->success(['path' => $path, 'items' => $items]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/sharepoint/files?path=
    // Daftar file dalam sebuah folder
    // ──────────────────────────────────────────────────────────────
    public function files()
    {
        $this->requireRole(['direktur','ketua_tim']);
        $path  = $this->request->getGet('path') ?: $this->libPath;
        $token = $this->_getAccessToken();
        if (!$token) return $this->fail('Gagal autentikasi ke SharePoint.', 401);

        $enc = rawurlencode("'{$path}'");
        $url = "{$this->siteUrl}/_api/web/GetFolderByServerRelativeUrl({$enc})/Files"
             . "?\$select=Name,ServerRelativeUrl,UniqueId,Length,TimeCreated,TimeLastModified"
             . "&\$orderby=TimeLastModified desc";

        $res = $this->_spGet($url, $token);
        if (!$res || isset($res['error']) || isset($res['odata.error'])) {
            return $this->fail('Gagal membaca file SharePoint.');
        }

        $items = array_map(fn($f) => [
            'name'      => $f['Name'],
            'path'      => $f['ServerRelativeUrl'],
            'unique_id' => $f['UniqueId']           ?? null,
            'size'      => $f['Length']             ?? 0,
            'created'   => $f['TimeCreated']        ?? null,
            'modified'  => $f['TimeLastModified']   ?? null,
            'web_url'   => $this->siteUrl . $f['ServerRelativeUrl'],
        ], $res['value'] ?? []);

        return $this->success(['path' => $path, 'items' => $items]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/sharepoint/file-meta?path=
    // Metadata satu file (properties SharePoint)
    // ──────────────────────────────────────────────────────────────
    public function fileMeta()
    {
        $this->requireRole(['direktur','ketua_tim']);
        $path = $this->request->getGet('path');
        if (!$path) return $this->fail('Parameter path wajib diisi.');

        $token = $this->_getAccessToken();
        if (!$token) return $this->fail('Gagal autentikasi.', 401);

        $enc = rawurlencode("'{$path}'");
        $url = "{$this->siteUrl}/_api/web/GetFileByServerRelativeUrl({$enc})"
             . "?\$select=Name,ServerRelativeUrl,UniqueId,Length,TimeCreated,TimeLastModified,Title";

        $res = $this->_spGet($url, $token);
        if (!$res || isset($res['error'])) {
            return $this->fail('File tidak ditemukan di SharePoint.');
        }

        return $this->success([
            'name'      => $res['Name']              ?? '',
            'path'      => $res['ServerRelativeUrl'] ?? $path,
            'unique_id' => $res['UniqueId']          ?? null,
            'size'      => $res['Length']            ?? 0,
            'created'   => $res['TimeCreated']       ?? null,
            'modified'  => $res['TimeLastModified']  ?? null,
            'web_url'   => $this->siteUrl . ($res['ServerRelativeUrl'] ?? $path),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/serasi/sharepoint/sync-folders
    // Sinkronisasi rekursif folder SharePoint → serasi_folders
    // ──────────────────────────────────────────────────────────────
    public function syncFolders()
    {
        $this->requireRole(['direktur']);
        $token = $this->_getAccessToken();
        if (!$token) return $this->fail('Gagal autentikasi.', 401);

        $model  = new SerasiFolderModel();
        $synced = 0;
        $this->_syncFolderRecursive($model, $token, $this->libPath, null, 0, 5, $synced);

        $this->auditLog('Sinkronisasi Folder SharePoint','serasi_folders',null,null,['synced'=>$synced]);
        return $this->success(['synced' => $synced], "Sinkronisasi selesai. {$synced} folder diperbarui.");
    }

    private function _syncFolderRecursive(
        SerasiFolderModel $model,
        string $token,
        string $path,
        ?int   $parentDbId,
        int    $depth,
        int    $maxDepth,
        int    &$synced
    ): void {
        if ($depth >= $maxDepth) return;

        $enc = rawurlencode("'{$path}'");
        $url = "{$this->siteUrl}/_api/web/GetFolderByServerRelativeUrl({$enc})/Folders"
             . "?\$select=Name,ServerRelativeUrl,ItemCount,UniqueId";

        $res = $this->_spGet($url, $token);
        if (!$res || empty($res['value'])) return;

        foreach ($res['value'] as $f) {
            $dbId = $model->upsertFromSharePoint($f, $parentDbId);
            $synced++;
            $this->_syncFolderRecursive($model, $token, $f['ServerRelativeUrl'], $dbId, $depth+1, $maxDepth, $synced);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/serasi/sharepoint/import-file
    // Import metadata satu file SharePoint → serasi_documents
    // Body: { "path": "/sites/DSK/Shared Documents/..." }
    // ──────────────────────────────────────────────────────────────
    public function importFile()
    {
        $this->requireRole(['direktur','ketua_tim']);
        $data = $this->request->getJSON(true);
        $path = trim($data['path'] ?? '');
        if (!$path) return $this->fail('Parameter path wajib diisi.');

        $token = $this->_getAccessToken();
        if (!$token) return $this->fail('Gagal autentikasi.', 401);

        $enc    = rawurlencode("'{$path}'");
        $url    = "{$this->siteUrl}/_api/web/GetFileByServerRelativeUrl({$enc})"
                . "?\$select=Name,ServerRelativeUrl,UniqueId,Length,TimeCreated,TimeLastModified";
        $spFile = $this->_spGet($url, $token);

        if (!$spFile || isset($spFile['error']) || isset($spFile['odata.error'])) {
            return $this->fail('File tidak ditemukan di SharePoint: ' . $path);
        }

        // Cari folder_id dari path folder
        $folderPath = dirname($path);
        $folderMdl  = new SerasiFolderModel();
        $folder     = $folderMdl->where('full_path', $folderPath)->first();
        $folderId   = $folder ? (int)$folder['folder_id'] : null;

        $docMdl  = new SerasiDocumentModel();
        $docId   = $docMdl->upsertFromSharePoint($spFile, $folderId ?? 0, $this->userId());

        $this->auditLog('Import File SharePoint','serasi_documents',$docId,null,['path'=>$path]);
        return $this->success(['document_id'=>$docId,'file_name'=>$spFile['Name']],
            "Metadata '{$spFile['Name']}' berhasil diimpor.",201);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/sharepoint/lists
    // Daftar document libraries di site
    // ──────────────────────────────────────────────────────────────
    public function lists()
    {
        $this->requireRole(['direktur']);
        $token = $this->_getAccessToken();
        if (!$token) return $this->fail('Gagal autentikasi.', 401);

        $url = "{$this->siteUrl}/_api/web/lists"
             . "?\$filter=BaseTemplate eq 101&\$select=Title,RootFolder/ServerRelativeUrl"
             . "&\$expand=RootFolder";

        $res = $this->_spGet($url, $token);
        if (!$res) return $this->fail('Gagal membaca document libraries.');

        $items = array_map(fn($l) => [
            'title' => $l['Title'],
            'path'  => $l['RootFolder']['ServerRelativeUrl'] ?? '',
        ], $res['value'] ?? []);

        return $this->success(['items' => $items]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function _isConfigured(): bool
    {
        if (empty($this->siteUrl) || empty($this->clientId) || empty($this->clientSecret)) {
            return false;
        }
        if ($this->authMethod === 'password') {
            return !empty($this->spUsername) && !empty($this->spPassword);
        }
        // azure: butuh tenantId; apponly: bisa ambil realm otomatis
        if ($this->authMethod === 'azure' && empty($this->tenantId)) {
            return false;
        }
        return true;
    }

    /**
     * Pilih metode autentikasi berdasarkan SHAREPOINT_AUTH_METHOD:
     *   azure    → OAuth2 Client Credentials via Azure AD (default)
     *   apponly  → SharePoint App-Only via ACS (AppInv.aspx, tanpa Azure Portal admin)
     *   password → Resource Owner Password Credentials — akun service non-MFA
     */
    private function _getAccessToken(): ?string
    {
        static $cached = null;
        if ($cached) return $cached;

        $host     = parse_url($this->siteUrl, PHP_URL_HOST);
        $resource = "https://{$host}";

        if ($this->authMethod === 'apponly') {
            $cached = $this->_getTokenAppOnly($resource);
        } elseif ($this->authMethod === 'password') {
            $cached = $this->_getTokenPassword($resource);
        } else {
            $cached = $this->_getTokenAzure($resource);
        }

        return $cached;
    }

    /** Metode 1: Azure AD Client Credentials (default) */
    private function _getTokenAzure(string $resource): ?string
    {
        if (empty($this->tenantId)) return null;

        $url  = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/token";
        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'resource'      => $resource,
        ]);

        $res = $this->_httpPost($url, $body);
        return $res['access_token'] ?? null;
    }

    /**
     * Metode 2: SharePoint App-Only (ACS / AppInv.aspx).
     * Tidak memerlukan Azure Portal Global Admin — cukup Site Collection Admin.
     *
     * Langkah setup (lihat panduan di panel SharePoint aplikasi):
     *   1. {SITE_URL}/_layouts/15/appregnew.aspx  → generate Client ID & Secret
     *   2. {SITE_URL}/_layouts/15/appinv.aspx      → trust app + beri permission
     *
     * Format SHAREPOINT_CLIENT_ID: <client-id>@<tenant-id>
     * Tenant ID bisa ditemukan otomatis dari endpoint SharePoint (tanpa login Azure).
     */
    private function _getTokenAppOnly(string $resource): ?string
    {
        $realm = !empty($this->tenantId) ? $this->tenantId : $this->_getSharePointRealm();
        if (!$realm) return null;

        $url  = "https://accounts.accesscontrol.windows.net/{$realm}/tokens/OAuth/2";
        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,   // format: <id>@<realm>
            'client_secret' => $this->clientSecret,
            'resource'      => "00000003-0000-0ff1-ce00-000000000000/{$resource}@{$realm}",
        ]);

        $res = $this->_httpPost($url, $body);
        return $res['access_token'] ?? null;
    }

    /**
     * Metode 3: Resource Owner Password Credentials (ROPC).
     * Cocok untuk service account organisasi (MFA dinonaktifkan untuk akun tersebut).
     * Tidak perlu daftarkan app baru — gunakan client_id Microsoft Office bawaan.
     */
    private function _getTokenPassword(string $resource): ?string
    {
        if (empty($this->tenantId) || empty($this->spUsername) || empty($this->spPassword)) {
            return null;
        }
        // Client ID Microsoft Office (tidak perlu daftarkan app sendiri)
        $clientId = !empty($this->clientId) ? $this->clientId
            : 'd3590ed6-52b3-4102-aeff-aad2292ab01c';

        $url  = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/token";
        $body = http_build_query([
            'grant_type'    => 'password',
            'client_id'     => $clientId,
            'username'      => $this->spUsername,
            'password'      => $this->spPassword,
            'resource'      => $resource,
        ]);

        $res = $this->_httpPost($url, $body);
        return $res['access_token'] ?? null;
    }

    /**
     * Ambil SharePoint Realm (Tenant ID) langsung dari SharePoint
     * tanpa perlu login Azure Portal sama sekali.
     * Cukup hit endpoint publik /_vti_bin/client.svc dan baca header WWW-Authenticate.
     */
    private function _getSharePointRealm(): ?string
    {
        $ch = curl_init("{$this->siteUrl}/_vti_bin/client.svc");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        // Header: WWW-Authenticate: Bearer realm="<tenant-id>",..."
        if (preg_match('/realm="([0-9a-f\-]{36})"/', (string)$response, $m)) {
            return $m[1];
        }
        return null;
    }

    /** HTTP POST helper → decode JSON response */
    private function _httpPost(string $url, string $body): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$res) return null;
        return json_decode($res, true) ?: null;
    }

    private function _spGet(string $url, string $token): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Accept: application/json;odata=nometadata',
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$res) return null;
        $decoded = json_decode($res, true);
        // Normalise: beberapa versi SP mengembalikan {"d":{"results":[]}}
        if (isset($decoded['d']['results'])) {
            return ['value' => $decoded['d']['results']] + $decoded['d'];
        }
        if (isset($decoded['d'])) return $decoded['d'];
        return $decoded;
    }
}
