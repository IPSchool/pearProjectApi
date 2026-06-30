<?php

use think\facade\Route;

if (!config('jira.enabled')) {
    return;
}

$issueKeyPattern = ['issueIdOrKey' => '[A-Za-z][A-Za-z0-9_]*-\d+|\d+'];

Route::group('rest/api/3', function () use ($issueKeyPattern) {
    Route::get('myself', 'jira/v3.Myself/index');
    Route::get('user/search', 'jira/v3.User/index');
    Route::get('user', 'jira/v3.User/index');
    Route::get('project/search', 'jira/v3.Project/search');
    Route::get('project/:projectKey', 'jira/v3.Project/read');
    Route::post('search', 'jira/v3.Search/index');

    Route::get('issue/<issueIdOrKey>/comment', 'jira/v3.IssueComment/index')
        ->pattern($issueKeyPattern);
    Route::post('issue/<issueIdOrKey>/comment', 'jira/v3.IssueComment/create')
        ->pattern($issueKeyPattern);
    Route::get('issue/<issueIdOrKey>/transitions', 'jira/v3.IssueTransition/index')
        ->pattern($issueKeyPattern);
    Route::post('issue/<issueIdOrKey>/transitions', 'jira/v3.IssueTransition/apply')
        ->pattern($issueKeyPattern);

    Route::post('issue', 'jira/v3.Issue/create');
    Route::get('issue/<issueIdOrKey>', 'jira/v3.Issue/read')
        ->pattern($issueKeyPattern);
    Route::put('issue/<issueIdOrKey>', 'jira/v3.Issue/update')
        ->pattern($issueKeyPattern);
    Route::delete('issue/<issueIdOrKey>', 'jira/v3.Issue/delete')
        ->pattern($issueKeyPattern);
})->middleware(\app\jira\middleware\JiraAuth::class);
