<?php

namespace App\Controllers;

use App\Models\Plan;
use App\Core\Response;

class PlanController {
    public function index() {
        $response = new Response();
        try {
            $plans = Plan::getActive();
            $response->success($plans);
        } catch (\Exception $e) {
            $response->error($e->getMessage());
        }
    }
}
