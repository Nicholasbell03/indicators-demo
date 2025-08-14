<?php

return [
    /**
     * The roles that can be responsible for submitting compliance indicators.
     * This is used to determine the responsible for a compliance indicator.
     */
    'responsible_roles' => [
        'entrepreneur',
        'mentor',
        'flowcoder',
        'guide',
        'programme-coordinator',
        'regional-coordinator',
    ],

    /**
     * The allowable roles that can be responsible for verifying success and compliance indicator tasks.
     * This is used to determine the verifier for an indicator task.
     */
    'verifier_roles' => [
        'mentor',
        'programme-manager',
        'programme-coordinator',
        'regional-coordinator',
        'regional-manager',
        'eso-manager',
    ],

    /**
     * The number of days to add when creating indicator review tasks' due dates.
     */
    'review_task_days' => env('INDICATOR_REVIEW_TASK_DAYS', 7),
    
    /**
     * The number of days until an indicator task is overdue.
     * This is used to determine the due date for indicator review tasks.
     */
    'days_until_overdue' => env('INDICATOR_DAYS_UNTIL_OVERDUE', 7),
];
