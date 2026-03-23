<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Licence;

class LicenceController {
    public function validate() {
        $request = new Request();
        $response = new Response();

        $key = $request->input('key');
        if (!$key) {
            $response->error("Licence key is required");
        }

        try {
            $licence = Licence::validate($key);
            if ($licence) {
                $response->success([
                    'valid' => true,
                    'store_name' => $licence['store_name'],
                    'subdomain' => $licence['subdomain'],
                    'expires_at' => $licence['expires_at']
                ], "Licence is valid");
            } else {
                $response->json([
                    'success' => true,
                    'valid' => false
                ], 200);
            }
        } catch (\Exception $e) {
            $response->error("Validation error: " . $e->getMessage());
        }
    }
}
