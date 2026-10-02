<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionSnapshot extends Model
{
    use HasFactory;

    protected $table = 'consumption_intelligence_snapshots';

    protected $fillable = [
        'item_variant_id',
        'warehouse_id',
        'period_start',
        'period_end',
        'valid_weeks_count',
        'active_weeks_count',
        'total_transactions',
        'total_consumption_qty',
        'prior_period_qty',
        'recent_period_qty',
        'prior_avg',
        'recent_avg',
        'ols_slope',
        'normalized_slope_pct',
        'recent_growth_pct',
        'volatility_cv',
        'stability_score',
        'confidence_score',
        'trend_signal',
        'trend_index',
        'classification',
        'meeting_eligible',
        'primary_department_driver',
        'top_requester_driver',
        'weekly_series_json',
        'attribution_json',
        'calculated_at',
    ];

    protected $casts = [
        'period_start'          => 'date',
        'period_end'            => 'date',
        'valid_weeks_count'     => 'integer',
        'active_weeks_count'    => 'integer',
        'total_transactions'    => 'integer',
        'total_consumption_qty' => 'integer',
        'prior_period_qty'      => 'integer',
        'recent_period_qty'     => 'integer',
        'prior_avg'             => 'float',
        'recent_avg'            => 'float',
        'ols_slope'             => 'float',
        'normalized_slope_pct'  => 'float',
        'recent_growth_pct'     => 'float',
        'volatility_cv'         => 'float',
        'stability_score'       => 'float',
        'confidence_score'      => 'float',
        'trend_signal'          => 'float',
        'trend_index'           => 'float',
        'meeting_eligible'      => 'boolean',
        'weekly_series_json'    => 'array',
        'attribution_json'      => 'array',
        'calculated_at'         => 'datetime',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
