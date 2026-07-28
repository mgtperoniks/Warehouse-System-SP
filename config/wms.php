<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Location Audit Coverage Status Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the thresholds in days for physical location audits:
    | - Green (Audited): Audited within the last N days.
    | - Yellow (Audit Aging/Stale): Audited within the last M days.
    | - Red (Needs Audit): Audited more than M days ago, or never audited.
    |
    */
    'audit_green_days' => env('WMS_AUDIT_GREEN_DAYS', 30),
    'audit_yellow_days' => env('WMS_AUDIT_YELLOW_DAYS', 90),
];
