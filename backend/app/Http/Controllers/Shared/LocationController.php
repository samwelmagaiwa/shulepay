<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\Place;
use App\Models\State;
use App\Models\Village;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * Get all regions (states).
     */
    public function regions(): JsonResponse
    {
        $regions = Cache::remember('locations.regions', 86400, function () {
            return State::select('id', 'name')
                ->orderBy('name')
                ->get();
        });

        return response()->json(['data' => $regions]);
    }

    /**
     * Get districts for a given region (state_id or region_name).
     */
    public function districts(Request $request): JsonResponse
    {
        $stateId = $request->state_id;

        if (! $stateId) {
            return response()->json(['data' => []]);
        }

        $cacheKey = "locations.districts.{$stateId}";

        $districts = Cache::remember($cacheKey, 86400, function () use ($stateId) {
            return Lga::query()
                ->select('id', 'name', 'state_id')
                ->where('state_id', $stateId)
                ->orderBy('name')
                ->get();
        });

        return response()->json(['data' => $districts]);
    }

    /**
     * Get wards for a given district (lga_id or district_name).
     */
    public function wards(Request $request): JsonResponse
    {
        $lgaId = $request->lga_id;

        if (! $lgaId) {
            return response()->json(['data' => []]);
        }

        $cacheKey = "locations.wards.{$lgaId}";

        $wards = Cache::remember($cacheKey, 86400, function () use ($lgaId) {
            return Ward::query()
                ->select('id', 'name', 'lga_id')
                ->where('lga_id', $lgaId)
                ->orderBy('name')
                ->get();
        });

        return response()->json(['data' => $wards]);
    }

    /**
     * Get streets/villages for a given ward (ward_id or ward_name).
     */
    public function streets(Request $request): JsonResponse
    {
        $wardId = $request->ward_id;

        if (! $wardId) {
            return response()->json(['data' => []]);
        }

        $cacheKey = "locations.streets.{$wardId}";

        $streets = Cache::remember($cacheKey, 86400, function () use ($wardId) {
            return Village::query()
                ->select('id', 'name', 'ward_id')
                ->where('ward_id', $wardId)
                ->orderBy('name')
                ->get();
        });

        return response()->json(['data' => $streets]);
    }

    /**
     * Get places for a given village/street (village_id).
     */
    public function places(Request $request): JsonResponse
    {
        $villageId = $request->village_id;

        if (! $villageId) {
            return response()->json(['data' => []]);
        }

        $cacheKey = "locations.places.{$villageId}";

        $places = Cache::remember($cacheKey, 86400, function () use ($villageId) {
            return Place::query()
                ->select('id', 'name', 'village_id')
                ->where('village_id', $villageId)
                ->orderBy('name')
                ->get();
        });

        return response()->json(['data' => $places]);
    }
}
