<?php

namespace App\Services;

use Exception;

class CPanelService {
    private $host;
    private $username;
    private $apiToken;
    private $isProduction;

    public function __construct() {
        $this->host = defined('CPANEL_HOST') ? CPANEL_HOST : '';
        $this->username = defined('CPANEL_USER') ? CPANEL_USER : '';
        $this->apiToken = defined('CPANEL_TOKEN') ? CPANEL_TOKEN : '';
        
        // Only run real API calls if production mode is active and credentials are set
        $this->isProduction = !empty($this->apiToken) && !empty($this->username);
    }

    /**
     * Create a Subdomain
     */
    public function createSubdomain($subdomain, $domain, $dir) {
        if (!$this->isProduction) {
            return ['success' => true, 'message' => 'Local Mode: Subdomain simulated'];
        }

        return $this->callUAPI('SubDomain', 'addsubdomain', [
            'domain'                => $subdomain,
            'rootdomain'            => $domain,
            'dir'                   => $dir,
            'disallowdot'           => 1
        ]);
    }

    /**
     * Create a Database
     */
    public function createDatabase($dbName) {
        if (!$this->isProduction) {
            return ['success' => true, 'message' => 'Local Mode: Database simulated'];
        }

        return $this->callUAPI('Mysql', 'create_database', [
            'name' => $dbName
        ]);
    }

    /**
     * Create a Database User
     */
    public function createDatabaseUser($user, $pass) {
        if (!$this->isProduction) {
            return ['success' => true, 'message' => 'Local Mode: DB User simulated'];
        }

        return $this->callUAPI('Mysql', 'create_user', [
            'name' => $user,
            'password' => $pass
        ]);
    }

    /**
     * Assign User to Database with All Privileges
     */
    public function setDatabasePrivileges($user, $db) {
        if (!$this->isProduction) {
            return ['success' => true, 'message' => 'Local Mode: Privileges simulated'];
        }

        return $this->callUAPI('Mysql', 'set_privileges_on_database', [
            'user' => $user,
            'database' => $db,
            'privileges' => 'ALL PRIVILEGES'
        ]);
    }

    /**
     * Internal UAPI Call Wrapper
     */
    private function callUAPI($module, $function, $params = []) {
        $query = "https://{$this->host}:2083/execute/{$module}/{$function}?" . http_build_query($params);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $query);

        $header = [
            "Authorization: cpanel {$this->username}:{$this->apiToken}"
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        $decoded = json_decode($result, true);
        
        if (!$decoded || (isset($decoded['status']) && $decoded['status'] == 0)) {
            $msg = isset($decoded['errors']) ? implode(', ', $decoded['errors']) : 'Unknown CPanel error';
            throw new Exception("CPanel API Error: " . $msg);
        }

        return [
            'success' => true,
            'data' => $decoded['data'] ?? []
        ];
    }
}
