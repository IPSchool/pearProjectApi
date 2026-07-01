<?php
return [
    'enabled'             => env('jira.enabled', true),
    'default_project_key' => env('jira.default_project_key', 'TST'),
    'gate_b_email'        => env('jira.gate_b_email', 'jira-test@example.com'),
    'gate_b_token'        => env('jira.gate_b_token', 'gate-b-test-token'),
    'gate_b_account_id'   => env('jira.gate_b_account_id', '712020:00000000-0000-4000-8000-gateb0000001'),
];
