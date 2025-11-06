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
    public function update(Request $request)
    {
        try {
            \Log::info('[UpdateController] Update request received', [
                'ip' => $request->ip(),
                'user' => $request->user()?->id,
            ]);

            $updateResult = $this->gitUpdateService->performUpdate();

            \Log::info('[UpdateController] Update result', $updateResult);

            // Return JSON for non-Inertia requests (API), Inertia response for Inertia requests
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json($updateResult);
            }

            // For Inertia requests, return the result as props
            return back()->with('updateResult', $updateResult);
        } catch (\Throwable $e) {
            \Log::error('[UpdateController] Update failed with exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorResult = [
                'success' => false,
                'message' => 'Update failed',
                'output' => null,
                'error' => $e->getMessage(),
            ];

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json($errorResult, 500);
            }

            return back()->with('updateResult', $errorResult);
        }
    }
}
