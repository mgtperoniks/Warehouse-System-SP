<?php

namespace App\Services\Intelligence;

use App\Models\ConsumptionSnapshot;
use App\Models\ItemVariant;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsumptionCalculationService
{
    // Operational validity constants
    public const VALID_WEEKS_TARGET = 8;
    public const VALID_WEEK_THRESHOLD_RATIO = 0.25; // 25% of median transactions
    public const MIN_WEEKLY_TRANSACTION_FLOOR = 5;

    // Classification constants
    public const CLASS_STRONG_RISING    = 'STRONG_RISING';
    public const CLASS_RISING           = 'RISING';
    public const CLASS_WATCH            = 'WATCH';
    public const CLASS_STABLE           = 'STABLE';
    public const CLASS_FALLING          = 'FALLING';
    public const CLASS_HIGH_VOLATILITY  = 'HIGH_VOLATILITY';
    public const CLASS_SPORADIC_SPIKE   = 'SPORADIC_SPIKE';
    public const CLASS_DORMANT          = 'DORMANT';
    public const CLASS_INSUFFICIENT     = 'INSUFFICIENT_DATA';

    /**
     * Compute and persist the latest analytical snapshot for all item variants.
     * High-speed optimized execution.
     */
    public function calculateAndPersistSnapshots(?int $warehouseId = null): array
    {
        $validWeeks = $this->determineValidOperationalWeeks(self::VALID_WEEKS_TARGET, $warehouseId);
        if (empty($validWeeks)) {
            return [
                'status'  => 'error',
                'message' => 'No valid operational weeks found in historical data.',
            ];
        }

        $n = count($validWeeks);
        $periodStart = $validWeeks[0]['start_date'];
        $periodEnd   = $validWeeks[$n - 1]['end_date'];
        $fullStartDt = $validWeeks[0]['start'];
        $fullEndDt   = $validWeeks[$n - 1]['end'];

        $half = (int) floor($n / 2);
        $priorStart = $validWeeks[0]['start'];
        $priorEnd   = $validWeeks[$half - 1]['end'];
        $recentStart = $validWeeks[$half]['start'];
        $recentEnd   = $validWeeks[$n - 1]['end'];

        // 1. Fetch all weekly consumptions in a single fast pivoted query
        // Group by item_variant_id
        $selects = ['sti.item_variant_id'];
        for ($w = 0; $w < $n; $w++) {
            $wStart = $validWeeks[$w]['start'];
            $wEnd   = $validWeeks[$w]['end'];
            $wNum   = $w + 1;
            $selects[] = DB::raw("SUM(CASE WHEN st.created_at >= '{$wStart}' AND st.created_at <= '{$wEnd}' THEN sti.qty ELSE 0 END) as w{$wNum}_qty");
            $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN st.created_at >= '{$wStart}' AND st.created_at <= '{$wEnd}' THEN st.id ELSE NULL END) as w{$wNum}_tx");
        }

        $query = DB::table('stock_transaction_items as sti')
            ->join('stock_transactions as st', 'sti.stock_transaction_id', '=', 'st.id')
            ->where('st.type', 'OUT')
            ->where('st.status', 'CONFIRMED')
            ->whereBetween('st.created_at', [$fullStartDt, $fullEndDt]);

        if ($warehouseId) {
            $query->where('st.warehouse_id', $warehouseId);
        }

        $rawAggregates = $query->select($selects)
            ->groupBy('sti.item_variant_id')
            ->get()
            ->keyBy('item_variant_id');

        // 2. Fetch all ItemVariants
        $allVariants = ItemVariant::with('item')->get();

        $snapshotsToUpsert = [];
        $now = now();
        $stats = [
            'total_variants' => $allVariants->count(),
            'active_variants'=> 0,
            'strong_rising'  => 0,
            'rising'         => 0,
            'watch'          => 0,
            'stable'         => 0,
            'volatile'       => 0,
            'sporadic'       => 0,
            'dormant'        => 0,
        ];

        foreach ($allVariants as $variant) {
            $vId = $variant->id;
            $row = $rawAggregates->get($vId);

            $quantities = [];
            $transactions = [];
            $weeklySeries = [];

            for ($w = 0; $w < $n; $w++) {
                $wNum = $w + 1;
                $qProp = "w{$wNum}_qty";
                $tProp = "w{$wNum}_tx";
                $q = $row ? (int) $row->$qProp : 0;
                $t = $row ? (int) $row->$tProp : 0;

                $quantities[] = $q;
                $transactions[] = $t;

                $weeklySeries[] = [
                    'week_num'   => $wNum,
                    'week_label' => $validWeeks[$w]['week_label'],
                    'start_date' => $validWeeks[$w]['start_date'],
                    'end_date'   => $validWeeks[$w]['end_date'],
                    'qty'        => $q,
                    'tx_count'   => $t,
                ];
            }

            $metrics = $this->calculateSkuMetrics($quantities, $transactions);

            $attribution = null;
            if ($metrics['active_weeks_count'] > 0) {
                $stats['active_variants']++;
                $attribution = $this->calculateAttribution($vId, $priorStart, $priorEnd, $recentStart, $recentEnd);
            }

            // Update stats
            switch ($metrics['classification']) {
                case self::CLASS_STRONG_RISING:
                    $stats['strong_rising']++;
                    break;
                case self::CLASS_RISING:
                    $stats['rising']++;
                    break;
                case self::CLASS_WATCH:
                    $stats['watch']++;
                    break;
                case self::CLASS_HIGH_VOLATILITY:
                    $stats['volatile']++;
                    break;
                case self::CLASS_SPORADIC_SPIKE:
                    $stats['sporadic']++;
                    break;
                case self::CLASS_DORMANT:
                    $stats['dormant']++;
                    break;
                default:
                    $stats['stable']++;
            }

            $snapshotsToUpsert[] = [
                'item_variant_id'           => $vId,
                'warehouse_id'              => $warehouseId,
                'period_start'              => $periodStart,
                'period_end'                => $periodEnd,
                'valid_weeks_count'         => $n,
                'active_weeks_count'        => $metrics['active_weeks_count'],
                'total_transactions'        => $metrics['total_transactions'],
                'total_consumption_qty'     => $metrics['total_consumption_qty'],
                'prior_period_qty'          => $metrics['prior_period_qty'],
                'recent_period_qty'         => $metrics['recent_period_qty'],
                'prior_avg'                 => $metrics['prior_avg'],
                'recent_avg'                => $metrics['recent_avg'],
                'ols_slope'                 => $metrics['ols_slope'],
                'normalized_slope_pct'      => $metrics['normalized_slope_pct'],
                'recent_growth_pct'         => $metrics['recent_growth_pct'],
                'volatility_cv'             => $metrics['volatility_cv'],
                'stability_score'           => $metrics['stability_score'],
                'confidence_score'          => $metrics['confidence_score'],
                'trend_signal'              => $metrics['trend_signal'],
                'trend_index'               => $metrics['trend_index'],
                'classification'            => $metrics['classification'],
                'meeting_eligible'          => $metrics['meeting_eligible'],
                'primary_department_driver' => $attribution ? $attribution['primary_department_driver'] : null,
                'top_requester_driver'      => $attribution ? $attribution['top_requester_driver'] : null,
                'weekly_series_json'        => json_encode($weeklySeries),
                'attribution_json'          => $attribution ? json_encode($attribution) : null,
                'calculated_at'             => $now,
                'created_at'                => $now,
                'updated_at'                => $now,
            ];
        }

        // 3. Clear existing snapshots for this period & warehouse, then bulk insert
        DB::transaction(function () use ($periodStart, $periodEnd, $warehouseId, $snapshotsToUpsert) {
            $deleteQuery = DB::table('consumption_intelligence_snapshots')
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd);

            if ($warehouseId) {
                $deleteQuery->where('warehouse_id', $warehouseId);
            } else {
                $deleteQuery->whereNull('warehouse_id');
            }

            $deleteQuery->delete();

            // Chunk insert
            foreach (array_chunk($snapshotsToUpsert, 500) as $chunk) {
                DB::table('consumption_intelligence_snapshots')->insert($chunk);
            }
        });

        return [
            'status'       => 'success',
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'valid_weeks'  => $validWeeks,
            'stats'        => $stats,
        ];
    }

    /**
     * Determine the latest N valid operational weeks from actual transaction history.
     * Skips weeks with anomalously low activity (e.g., factory holiday shutdowns).
     *
     * @param int $targetCount
     * @param int|null $warehouseId
     * @return array Array of week windows ordered chronologically (oldest W_N to newest W_1)
     */
    public function determineValidOperationalWeeks(int $targetCount = self::VALID_WEEKS_TARGET, ?int $warehouseId = null): array
    {
        // 1. Get the latest transaction timestamp
        $latestTxQuery = StockTransaction::where('type', 'OUT')
            ->where('status', 'CONFIRMED');

        if ($warehouseId) {
            $latestTxQuery->where('warehouse_id', $warehouseId);
        }

        $latestTx = $latestTxQuery->orderBy('created_at', 'desc')->first();

        if (!$latestTx) {
            return [];
        }

        $refDate = Carbon::parse($latestTx->created_at, config('app.timezone'))->endOfDay();

        // 2. Scan back candidate 7-day windows up to 24 weeks
        $candidateWindows = [];
        for ($i = 0; $i < 24; $i++) {
            $end = (clone $refDate)->subDays($i * 7);
            $start = (clone $refDate)->subDays(($i + 1) * 7)->addSecond();

            $txCountQuery = StockTransaction::where('type', 'OUT')
                ->where('status', 'CONFIRMED')
                ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()]);

            if ($warehouseId) {
                $txCountQuery->where('warehouse_id', $warehouseId);
            }

            $txCount = $txCountQuery->count();

            $candidateWindows[] = [
                'candidate_index' => $i + 1,
                'start'           => $start->toDateTimeString(),
                'end'             => $end->toDateTimeString(),
                'start_date'      => $start->toDateString(),
                'end_date'        => $end->toDateString(),
                'start_carbon'    => $start,
                'end_carbon'      => $end,
                'tx_count'        => $txCount,
            ];
        }

        // 3. Compute median of non-zero weeks to establish dynamic threshold
        $txCounts = array_filter(array_column($candidateWindows, 'tx_count'), fn($c) => $c > 0);
        sort($txCounts);
        $count = count($txCounts);
        $median = $count > 0 ? ($count % 2 === 0 ? ($txCounts[$count / 2 - 1] + $txCounts[$count / 2]) / 2 : $txCounts[floor($count / 2)]) : 0;

        $threshold = max(self::MIN_WEEKLY_TRANSACTION_FLOOR, (int) round($median * self::VALID_WEEK_THRESHOLD_RATIO));

        // 4. Filter candidate windows that pass the validity threshold
        $validWindows = [];
        foreach ($candidateWindows as $w) {
            if ($w['tx_count'] >= $threshold) {
                $validWindows[] = $w;
                if (count($validWindows) >= $targetCount) {
                    break;
                }
            }
        }

        // If we still have fewer than target, take all valid ones
        // Reverse array so that index 0 is oldest week (t=1) and index N-1 is newest week (t=N)
        $validWindowsChronological = array_reverse($validWindows);

        // Re-index week_number 1 (oldest) to N (newest)
        $result = [];
        $n = count($validWindowsChronological);
        foreach ($validWindowsChronological as $idx => $win) {
            $win['week_index'] = $idx + 1; // 1 = oldest, N = newest
            $win['week_label'] = "W" . ($idx + 1);
            $win['threshold']  = $threshold;
            $result[] = $win;
        }

        return $result;
    }

    /**
     * Pure statistical calculation for a single SKU over a chronological sequence of weekly data.
     *
     * @param array $quantities Array of int/float quantities [y_1, y_2, ..., y_N] where y_1 is oldest, y_N is newest.
     * @param array $transactions Array of int transaction counts [k_1, k_2, ..., k_N]
     * @param array $extraContext Metadata (e.g. SKU creation date, item details)
     * @return array Calculated statistical metrics, indicators, and classification
     */
    public function calculateSkuMetrics(array $quantities, array $transactions = [], array $extraContext = []): array
    {
        $n = count($quantities);

        if ($n < 2) {
            return $this->buildEmptyMetricsResult($n, self::CLASS_INSUFFICIENT);
        }

        if (empty($transactions) || count($transactions) !== $n) {
            $transactions = array_map(fn($q) => $q > 0 ? 1 : 0, $quantities);
        }

        $totalQty = (int) array_sum($quantities);
        $totalTrx = (int) array_sum($transactions);

        // Count active weeks (weeks with quantity > 0)
        $activeWeeks = 0;
        $nonZeroQtys = [];
        foreach ($quantities as $q) {
            if ($q > 0) {
                $activeWeeks++;
                $nonZeroQtys[] = (float) $q;
            }
        }

        // Handle Dormant SKU (0 consumption across all weeks)
        if ($totalQty === 0 || $activeWeeks === 0) {
            return $this->buildEmptyMetricsResult($n, self::CLASS_DORMANT, $extraContext);
        }

        // 1. OLS Linear Regression on chronological sequence t = 1..N
        $t = range(1, $n);
        $meanT = ($n + 1) / 2.0;
        $meanY = $totalQty / (float) $n;

        $covTY = 0.0;
        $varT  = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $covTY += ($t[$i] - $meanT) * ($quantities[$i] - $meanY);
            $varT  += pow($t[$i] - $meanT, 2);
        }

        $slope = $varT > 0 ? ($covTY / $varT) : 0.0;

        // Normalized slope (% change of mean consumption per week)
        $normalizedSlopePct = ($slope / ($meanY + 1.0)) * 100.0;

        // 2. Recent vs Prior Half Growth
        $half = (int) floor($n / 2);
        $priorQtys = array_slice($quantities, 0, $half);
        $recentQtys = array_slice($quantities, $half);

        $priorSum = array_sum($priorQtys);
        $recentSum = array_sum($recentQtys);

        $priorAvg = count($priorQtys) > 0 ? ($priorSum / (float) count($priorQtys)) : 0.0;
        $recentAvg = count($recentQtys) > 0 ? ($recentSum / (float) count($recentQtys)) : 0.0;

        // Laplace-smoothed growth percentage
        $recentGrowthPct = (($recentAvg - $priorAvg) / ($priorAvg + 1.0)) * 100.0;

        // 3. Volatility / Stability (using Non-Zero observations to prevent intermittent blowup)
        $m = count($nonZeroQtys);
        $cvNz = 0.0;
        if ($m >= 2) {
            $meanNz = array_sum($nonZeroQtys) / (float) $m;
            $sqDiff = 0.0;
            foreach ($nonZeroQtys as $val) {
                $sqDiff += pow($val - $meanNz, 2);
            }
            $stdDevNz = sqrt($sqDiff / ($m - 1));
            $cvNz = $meanNz > 0 ? ($stdDevNz / ($meanNz + 0.1)) : 0.0;
        } elseif ($m === 1) {
            // Exactly 1 non-zero observation
            $cvNz = 0.0; // Handled heavily by confidence penalty instead
        }

        // Stability factor in (0, 1]
        $stabilityScore = 1.0 / (1.0 + $cvNz);

        // 4. Evidence / Confidence Quality Component
        // Considers: active week ratio, transaction density, and realistic volume scale (36 units)
        $weekRatio = $activeWeeks / (float) $n;
        $cWeeks = pow($weekRatio, 0.80);
        $cFreq  = min(1.0, $totalTrx / 6.0);
        $cVol   = min(1.0, pow($totalQty / 36.0, 0.5)); // Saturates at 36 units (was 6)

        // Combined Confidence in [0.0, 1.0] (0.65 frequency + 0.35 volume)
        $confidenceScore = $cWeeks * (0.65 * $cFreq + 0.35 * $cVol);

        // Severe penalty for isolated sporadic single-week spikes
        if ($activeWeeks === 1) {
            $confidenceScore = min($confidenceScore, 0.20);
        }

        // 5. Trend Signal (Sigmoid mapping to [0, 100] centered at 50)
        // Reference scales: 6.0% weekly normalized slope and 20.0% half-period growth
        $z = 0.55 * ($normalizedSlopePct / 6.0) + 0.45 * ($recentGrowthPct / 20.0);
        // Bound Z between -10 and 10 to prevent overflow
        $z = max(-10.0, min(10.0, $z));
        $trendSignal = 100.0 / (1.0 + exp(-$z));

        // 6. Final Composite Trend Index (0 - 100)
        // Multiplicative robust engine
        $trendIndex = $trendSignal * $confidenceScore * pow($stabilityScore, 0.40);
        $trendIndex = round(max(0.0, min(100.0, $trendIndex)), 1);

        // 7. Meeting Evidence Gate (Deterministic check for material management discussion)
        $meetingEligible = ($recentSum >= 15 || $totalQty >= 30 || $totalTrx >= 10);

        // 8. Classification logic
        $classification = $this->determineClassification(
            $activeWeeks,
            $n,
            $trendIndex,
            $normalizedSlopePct,
            $recentGrowthPct,
            $stabilityScore,
            $cvNz,
            $totalQty,
            $meetingEligible,
            $extraContext
        );

        return [
            'valid_weeks_count'    => $n,
            'active_weeks_count'   => $activeWeeks,
            'total_transactions'   => $totalTrx,
            'total_consumption_qty'=> $totalQty,
            'prior_period_qty'     => (int) $priorSum,
            'recent_period_qty'    => (int) $recentSum,
            'prior_avg'            => round($priorAvg, 2),
            'recent_avg'           => round($recentAvg, 2),
            'ols_slope'            => round($slope, 3),
            'normalized_slope_pct' => round($normalizedSlopePct, 2),
            'recent_growth_pct'    => round($recentGrowthPct, 1),
            'volatility_cv'        => round($cvNz, 3),
            'stability_score'      => round($stabilityScore, 4),
            'confidence_score'     => round($confidenceScore, 4),
            'trend_signal'         => round($trendSignal, 1),
            'trend_index'          => $trendIndex,
            'meeting_eligible'     => $meetingEligible,
            'classification'       => $classification,
            'quantities_series'    => $quantities,
            'transactions_series'  => $transactions,
        ];
    }

    /**
     * Deterministic classification rule engine.
     */
    protected function determineClassification(
        int $activeWeeks,
        int $totalWeeks,
        float $trendIndex,
        float $normSlope,
        float $recentGrowth,
        float $stability,
        float $cvNz,
        int $totalQty,
        bool $meetingEligible,
        array $extraContext
    ): string {
        if ($activeWeeks === 0 || $totalQty === 0) {
            return self::CLASS_DORMANT;
        }

        if ($activeWeeks === 1) {
            return self::CLASS_SPORADIC_SPIKE;
        }

        if ($totalWeeks < 4) {
            return self::CLASS_INSUFFICIENT;
        }

        // High Volatility: active in >= 3 weeks, unstable CV > 0.85, stability < 0.54, but not high index
        if ($activeWeeks >= 3 && $cvNz > 0.85 && $trendIndex < 60.0) {
            return self::CLASS_HIGH_VOLATILITY;
        }

        // Strong Rising: High index, regular presence (>= 4 weeks), positive slope & strong recent growth, and MUST be meeting eligible
        if ($trendIndex >= 68.0 && $activeWeeks >= 4 && $recentGrowth >= 15.0 && $normSlope > 0.0) {
            return $meetingEligible ? self::CLASS_STRONG_RISING : self::CLASS_WATCH;
        }

        // Rising: Moderate-to-high index, active in >= 2 weeks, positive trend, and MUST be meeting eligible
        if ($trendIndex >= 50.0 && $activeWeeks >= 2 && $recentGrowth > 5.0 && $normSlope > 0.0) {
            return $meetingEligible ? self::CLASS_RISING : self::CLASS_WATCH;
        }

        // Falling: Low index, active in >= 3 weeks, negative slope & shrinking recent
        if ($trendIndex < 35.0 && $activeWeeks >= 3 && $normSlope < -5.0 && $recentGrowth < -15.0) {
            return self::CLASS_FALLING;
        }

        // Watchlist: SKU with emerging positive signals or intermittent surges (including low-volume climbers)
        if ($trendIndex >= 40.0 && ($activeWeeks >= 2 || $recentGrowth > 30.0)) {
            return self::CLASS_WATCH;
        }

        return self::CLASS_STABLE;
    }

    /**
     * Calculate deterministic department and requester attribution for a SKU.
     */
    public function calculateAttribution(int $variantId, string $priorStart, string $priorEnd, string $recentStart, string $recentEnd): array
    {
        // 1. Fetch item detail rows in prior and recent period
        $priorRows = DB::table('stock_transaction_items')
            ->join('stock_transactions', 'stock_transaction_items.stock_transaction_id', '=', 'stock_transactions.id')
            ->where('stock_transaction_items.item_variant_id', $variantId)
            ->where('stock_transactions.type', 'OUT')
            ->where('stock_transactions.status', 'CONFIRMED')
            ->whereBetween('stock_transactions.created_at', [$priorStart, $priorEnd])
            ->select('stock_transaction_items.qty', 'stock_transactions.department_id', 'stock_transactions.user_id')
            ->get();

        $recentRows = DB::table('stock_transaction_items')
            ->join('stock_transactions', 'stock_transaction_items.stock_transaction_id', '=', 'stock_transactions.id')
            ->where('stock_transaction_items.item_variant_id', $variantId)
            ->where('stock_transactions.type', 'OUT')
            ->where('stock_transactions.status', 'CONFIRMED')
            ->whereBetween('stock_transactions.created_at', [$recentStart, $recentEnd])
            ->select('stock_transaction_items.qty', 'stock_transactions.department_id', 'stock_transactions.user_id')
            ->get();

        $priorByDept = [];
        foreach ($priorRows as $r) {
            $dId = $r->department_id ?: 0;
            $priorByDept[$dId] = ($priorByDept[$dId] ?? 0) + $r->qty;
        }

        $recentByDept = [];
        $recentByUser = [];
        foreach ($recentRows as $r) {
            $dId = $r->department_id ?: 0;
            $uId = $r->user_id ?: 0;
            $recentByDept[$dId] = ($recentByDept[$dId] ?? 0) + $r->qty;
            $recentByUser[$uId] = ($recentByUser[$uId] ?? 0) + $r->qty;
        }

        // Get Department names
        $deptIds = array_unique(array_merge(array_keys($priorByDept), array_keys($recentByDept)));
        $departments = DB::table('departments')->whereIn('id', $deptIds)->pluck('name', 'id')->toArray();
        $departments[0] = 'Unassigned / Umum';

        // Get User names
        $userIds = array_keys($recentByUser);
        $users = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')->toArray();
        $users[0] = 'Unassigned';

        // Compute Delta and Contribution %
        $deptContributions = [];
        $positiveDeltasSum = 0;

        foreach ($deptIds as $dId) {
            $p = $priorByDept[$dId] ?? 0;
            $r = $recentByDept[$dId] ?? 0;
            $delta = $r - $p;
            if ($delta > 0) {
                $positiveDeltasSum += $delta;
            }
            $deptContributions[$dId] = [
                'department_id'   => $dId,
                'department_name' => $departments[$dId] ?? "Dept #{$dId}",
                'prior_qty'       => $p,
                'recent_qty'      => $r,
                'delta_qty'       => $delta,
                'growth_pct'      => $p > 0 ? round((($r - $p) / $p) * 100, 1) : ($r > 0 ? 100.0 : 0.0),
            ];
        }

        // Calculate contribution shares
        foreach ($deptContributions as $dId => &$info) {
            if ($info['delta_qty'] > 0 && $positiveDeltasSum > 0) {
                $info['contribution_share_pct'] = round(($info['delta_qty'] / $positiveDeltasSum) * 100.0, 1);
            } else {
                $info['contribution_share_pct'] = 0.0;
            }
        }
        unset($info);

        // Sort departments by contribution share DESC then delta DESC
        usort($deptContributions, fn($a, $b) => $b['delta_qty'] <=> $a['delta_qty']);

        // Sort users by recent quantity DESC
        $userList = [];
        foreach ($recentByUser as $uId => $qty) {
            $userList[] = [
                'user_id'   => $uId,
                'user_name' => $users[$uId] ?? "User #{$uId}",
                'qty'       => $qty,
            ];
        }
        usort($userList, fn($a, $b) => $b['qty'] <=> $a['qty']);

        $primaryDept = !empty($deptContributions) ? $deptContributions[0]['department_name'] : null;
        $topRequester = !empty($userList) ? $userList[0]['user_name'] : null;

        return [
            'primary_department_driver' => $primaryDept,
            'top_requester_driver'      => $topRequester,
            'departments'               => $deptContributions,
            'top_requesters'            => array_slice($userList, 0, 5),
        ];
    }

    /**
     * Fallback for empty/insufficient/dormant metrics.
     */
    protected function buildEmptyMetricsResult(int $n, string $classification, array $extraContext = []): array
    {
        return [
            'valid_weeks_count'    => $n,
            'active_weeks_count'   => 0,
            'total_transactions'   => 0,
            'total_consumption_qty'=> 0,
            'prior_period_qty'     => 0,
            'recent_period_qty'    => 0,
            'prior_avg'            => 0.0,
            'recent_avg'           => 0.0,
            'ols_slope'            => 0.0,
            'normalized_slope_pct' => 0.0,
            'recent_growth_pct'    => 0.0,
            'volatility_cv'        => 0.0,
            'stability_score'      => 1.0,
            'confidence_score'     => 0.0,
            'trend_signal'         => 50.0,
            'trend_index'          => 0.0,
            'meeting_eligible'     => false,
            'classification'       => $classification,
            'quantities_series'    => array_fill(0, $n, 0),
            'transactions_series'  => array_fill(0, $n, 0),
        ];
    }
}
