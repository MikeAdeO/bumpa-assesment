<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AchievementProgressService;
use Illuminate\Http\JsonResponse;

class AchievementController extends Controller
{
    /**
     * Create the achievement controller with the progress service.
     */
    public function __construct(
        private readonly AchievementProgressService $progressService
    ) {
        //
    }

    /**
     * Return the user's achievement and badge progress.
     */
    public function index(User $user): JsonResponse
    {
        return response()->json(
            $this->progressService->getProgress($user)
        );
    }
}
