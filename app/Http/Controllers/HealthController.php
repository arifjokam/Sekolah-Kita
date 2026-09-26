<?php

namespace App\Http\Controllers;

use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Liveness probe.
     *
     * Intentionally dependency free so an outage in MariaDB or Redis cannot
     * cause an upstream load balancer to remove every application node.
     */
    public function live(HealthCheckService $health): JsonResponse
    {
        return response()->json($health->liveness());
    }

    /**
     * Readiness probe.
     *
     * Verifies the dependencies a real request needs, and returns 503 when
     * the instance must be taken out of rotation.
     */
    public function ready(HealthCheckService $health): JsonResponse
    {
        $result = $health->check();

        return response()->json($result, $result['healthy'] ? 200 : 503);
    }
}
