<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Store;

class DomainController {
    public function checkAvailability() {
        $request = new Request();
        $response = new Response();

        $subdomain = $request->input('subdomain');
        if (!$subdomain) {
            $response->error("Subdomain is required");
        }

        // Strict validation: 3-63 chars, alphanumeric and hyphens, cannot start/end with hyphen
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/i', $subdomain)) {
            $response->error("Invalid subdomain format. Must be 3-63 characters, alphanumeric and hyphens only, and cannot start or end with a hyphen.");
        }

        try {
            $existing = Store::findBySubdomain($subdomain);
            if ($existing) {
                $response->success(['available' => false], "Subdomain is already taken");
            } else {
                $response->success(['available' => true], "Subdomain is available");
            }
        } catch (\Exception $e) {
            $response->error("Check failed: " . $e->getMessage());
        }
    }
}
