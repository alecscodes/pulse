<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\GitUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __construct(
        public GitUpdateService $gitUpdateService
    ) {}

    /**
     * Check if updates are available.
     */
    public function check(Request $request): JsonResponse
    {
        $updateInfo = $this->gitUpdateService->checkForUpdates();

        return response()->json($updateInfo);
    }

    /**
     * Perform the update.
     */
    public function update(Request $request): JsonResponse
    {
        $updateResult = $this->gitUpdateService->performUpdate();

        return response()->json($updateResult);
    }
}
