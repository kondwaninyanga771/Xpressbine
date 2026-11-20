<?php

/**
 * Suggested defaults from the Xpressbine system and user definitions.
 * Every number is overridable with an environment variable, and admins
 * can override the editable keys at runtime via platform_settings.
 */

return [

    // json keeps every table in storage/app/data/db.json. database uses DB_*.
    'data_driver' => env('XPRESSBINE_DATA_DRIVER', 'json'),

    'currency' => env('XPRESSBINE_CURRENCY', 'ZMW'),

    'commission_rate' => env('XPRESSBINE_COMMISSION_RATE', '0.12'),
    'claim_window_hours' => (int) env('XPRESSBINE_CLAIM_WINDOW_HOURS', 48),
    'auto_release_hours' => (int) env('XPRESSBINE_AUTO_RELEASE_HOURS', 48),
    'full_refund_cancel_hours' => (int) env('XPRESSBINE_FULL_REFUND_CANCEL_HOURS', 24),
    'late_cancel_fee_rate' => env('XPRESSBINE_LATE_CANCEL_FEE_RATE', '0.15'),
    'call_out_fee_rate' => env('XPRESSBINE_CALL_OUT_FEE_RATE', '0.15'),
    'no_show_grace_minutes' => (int) env('XPRESSBINE_NO_SHOW_GRACE_MINUTES', 60),
    'fix_window_hours' => (int) env('XPRESSBINE_FIX_WINDOW_HOURS', 48),
    'client_absent_claim_hours' => (int) env('XPRESSBINE_CLIENT_ABSENT_CLAIM_HOURS', 48),
    'appeal_window_days' => (int) env('XPRESSBINE_APPEAL_WINDOW_DAYS', 7),
    'suspension_days_first' => (int) env('XPRESSBINE_SUSPENSION_DAYS_FIRST', 7),
    'suspension_days_second' => (int) env('XPRESSBINE_SUSPENSION_DAYS_SECOND', 30),
    'max_strikes' => (int) env('XPRESSBINE_MAX_STRIKES', 3),
    'low_rating_threshold' => env('XPRESSBINE_LOW_RATING_THRESHOLD', '2.5'),
    'low_rating_min_jobs' => (int) env('XPRESSBINE_LOW_RATING_MIN_JOBS', 10),
    'noshow_limit' => (int) env('XPRESSBINE_NOSHOW_LIMIT', 3),
    'noshow_window_days' => (int) env('XPRESSBINE_NOSHOW_WINDOW_DAYS', 30),
    'emergency_budget_rate' => env('XPRESSBINE_EMERGENCY_BUDGET_RATE', '0.18'),
    'budget_alert_warn' => env('XPRESSBINE_BUDGET_ALERT_WARN', '0.80'),
    'budget_alert_full' => env('XPRESSBINE_BUDGET_ALERT_FULL', '1.00'),
    'default_search_radius_km' => env('XPRESSBINE_DEFAULT_SEARCH_RADIUS_KM', '15'),
    'default_service_radius_km' => env('XPRESSBINE_DEFAULT_SERVICE_RADIUS_KM', '15'),
    'default_job_duration_minutes' => (int) env('XPRESSBINE_DEFAULT_JOB_DURATION_MINUTES', 120),
    'claim_review_threshold' => (int) env('XPRESSBINE_CLAIM_REVIEW_THRESHOLD', 3),
    'claim_review_window_days' => (int) env('XPRESSBINE_CLAIM_REVIEW_WINDOW_DAYS', 30),

    'ai' => [
        'driver' => env('XPRESSBINE_AI_DRIVER', 'mock'),
        'api_key' => env('XPRESSBINE_AI_API_KEY'),
        'base_url' => env('XPRESSBINE_AI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('XPRESSBINE_AI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('XPRESSBINE_AI_TIMEOUT', 30),
    ],

    'urgency_multipliers' => [
        'low' => '0.90',
        'medium' => '1.00',
        'high' => '1.15',
        'emergency' => '1.35',
    ],

    /*
     * Admin-editable keys and the numeric range that is accepted.
     * Keys not listed here stay on the env/config value.
     */
    'editable_settings' => [
        'commission_rate' => ['min' => 0, 'max' => 0.30],
        'claim_window_hours' => ['min' => 1, 'max' => 336],
        'auto_release_hours' => ['min' => 1, 'max' => 336],
        'full_refund_cancel_hours' => ['min' => 0, 'max' => 336],
        'late_cancel_fee_rate' => ['min' => 0, 'max' => 0.50],
        'call_out_fee_rate' => ['min' => 0, 'max' => 0.50],
        'no_show_grace_minutes' => ['min' => 0, 'max' => 360],
        'fix_window_hours' => ['min' => 1, 'max' => 336],
        'client_absent_claim_hours' => ['min' => 1, 'max' => 336],
        'appeal_window_days' => ['min' => 1, 'max' => 30],
        'suspension_days_first' => ['min' => 1, 'max' => 90],
        'suspension_days_second' => ['min' => 1, 'max' => 180],
        'max_strikes' => ['min' => 1, 'max' => 10],
        'low_rating_threshold' => ['min' => 1, 'max' => 5],
        'low_rating_min_jobs' => ['min' => 1, 'max' => 200],
        'noshow_limit' => ['min' => 1, 'max' => 20],
        'noshow_window_days' => ['min' => 1, 'max' => 365],
        'emergency_budget_rate' => ['min' => 0, 'max' => 1],
        'budget_alert_warn' => ['min' => 0, 'max' => 1],
        'budget_alert_full' => ['min' => 0, 'max' => 2],
        'default_search_radius_km' => ['min' => 1, 'max' => 200],
        'claim_review_threshold' => ['min' => 1, 'max' => 50],
    ],

];
