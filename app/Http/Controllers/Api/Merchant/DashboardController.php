<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Parking;
use App\Models\MerchantIncome;
use App\Models\MerchantWithdrawal;
use App\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * @group MerchantApi 
 * @subgroup Dashboard
 */
class DashboardController extends Controller
{
    /**
     * Merchant Income Stats
     */
    public function income(Request $request)
    {
        $merchant = $request->user();

        $now = Carbon::now();
        
        // Use startOfMonth and endOfMonth for robust month filtering
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        
        // Use subMonthNoOverflow to avoid landing on the wrong month (e.g., March 31 -> March 3)
        $lastMonthDate = $now->copy()->subMonthNoOverflow();
        $lastMonthStart = $lastMonthDate->copy()->startOfMonth();
        $lastMonthEnd = $lastMonthDate->copy()->endOfMonth();

        $currentMonthIncome = MerchantIncome::where('merchant_id', $merchant->id)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        $lastMonthIncome = MerchantIncome::where('merchant_id', $merchant->id)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $percentageChange = 0;
        if ($lastMonthIncome > 0) {
            $percentageChange = (($currentMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100;
        } elseif ($currentMonthIncome > 0) {
            $percentageChange = 100; // 100% growth if starting from zero
        }

        return apiResponse(true, 'Income stats fetched successfully', [
            'total_income' => (float)$currentMonthIncome, // "Total Income (current month)"
            'current_month_income' => (float)$currentMonthIncome,
            'last_month_income' => (float)$lastMonthIncome,
            'percentage_change' => round($percentageChange, 2),
            'currency' => 'pts',
            'comparison_text' => round($percentageChange, 2) . '% ' . ($percentageChange >= 0 ? 'increase' : 'decrease') . ' from last month'
        ]);
    }

    /**
     * Top Performing Routes
     */
    public function topRoutes(Request $request)
    {
        $merchant = $request->user();

        $routes = DB::table('merchant_incomes')
            ->join('buses', 'merchant_incomes.reference_id', '=', 'buses.id')
            ->join('routes', 'buses.route_id', '=', 'routes.id')
            ->where('merchant_incomes.merchant_id', $merchant->id)
            ->where('merchant_incomes.reference_type', Bus::class)
            ->select('routes.id', 'routes.name', DB::raw('SUM(merchant_incomes.amount) as total_revenue'))
            ->groupBy('routes.id', 'routes.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return apiResponse(true, 'Top routes fetched successfully', $routes);
    }

    /**
     * Top Performing Parkings
     */
    public function topParkings(Request $request)
    {
        $merchant = $request->user();

        $parkings = DB::table('merchant_incomes')
            ->join('parkings', 'merchant_incomes.reference_id', '=', 'parkings.id')
            ->where('merchant_incomes.merchant_id', $merchant->id)
            ->where('merchant_incomes.reference_type', Parking::class)
            ->select('parkings.id', 'parkings.name', DB::raw('SUM(merchant_incomes.amount) as total_revenue'))
            ->groupBy('parkings.id', 'parkings.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return apiResponse(true, 'Top parkings fetched successfully', $parkings);
    }

    /**
     * Withdrawal History
     */
    public function withdrawals(Request $request)
    {
        $merchant = $request->user();

        $withdrawals = MerchantWithdrawal::where('merchant_id', $merchant->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return apiResponse(true, 'Withdrawal history fetched successfully', $withdrawals);
    }

    /**
     * Nearby Arrivals
     */
    public function nearbyArrivals(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'radius' => 'nullable|numeric',
        ]);

        $lat = $request->lat;
        $lng = $request->long;
        $radius = $request->get('radius', 2);

        $haversine = "(6371 * acos(cos(radians({$lat})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$lng})) + sin(radians({$lat})) * sin(radians(latitude))))";
        
        $isSqlite = config('database.default') === 'sqlite';
        
        $query = Stop::select('*')
            ->selectRaw("{$haversine} AS distance");

        if ($isSqlite) {
            $nearbyStops = $query->get()->where('distance', '<=', $radius)->sortBy('distance');
        } else {
            $nearbyStops = $query->having('distance', '<=', $radius)
                ->orderBy('distance')
                ->get();
        }

        $arrivals = [];

        foreach ($nearbyStops as $stop) {
            $routeIds = DB::table('route_stops')->where('stop_id', $stop->id)->pluck('route_id');
            
            $buses = Bus::whereIn('route_id', $routeIds)
                ->where('status', 'active')
                ->with(['route', 'currentPosition'])
                ->get();

            foreach ($buses as $bus) {
                if ($bus->currentPosition) {
                    $busLat = $bus->currentPosition->latitude;
                    $busLng = $bus->currentPosition->longitude;
                    $distToStop = (6371 * acos(cos(deg2rad($busLat)) * cos(deg2rad($stop->latitude)) * cos(deg2rad($stop->longitude) - deg2rad($busLng)) + sin(deg2rad($busLat)) * sin(deg2rad($stop->latitude))));
                    
                    $arrivals[] = [
                        'stop_name' => $stop->name,
                        'bus_name' => $bus->name,
                        'bus_number' => $bus->bus_number,
                        'route_name' => $bus->route->name,
                        'distance_km' => round($distToStop, 2),
                        'estimated_arrival_minutes' => round(($distToStop / 20) * 60, 0),
                        'is_merchant_bus' => $bus->merchant_id == $request->user()->id
                    ];
                }
            }
        }

        usort($arrivals, fn($a, $b) => $a['estimated_arrival_minutes'] <=> $b['estimated_arrival_minutes']);

        return apiResponse(true, 'Nearby arrivals fetched successfully', array_slice($arrivals, 0, 10));
    }
}
