<?php
// +----------------------------------------------------------------------
// | Jira REST API v3 路由（Gate B — ThinkPHP 6）
// | 路由目标须为 Class@method 字符串（TP6 Rule::dispatch 不支持 [class, method] 数组）
// +----------------------------------------------------------------------
use app\jira\middleware\JiraAuth;
use think\facade\Route;

if (!config('jira.enabled')) {
    return;
}

// jira-python / 部分客户端初始化（无认证）
Route::get('rest/api/2/serverInfo', 'app\jira\controller\v2\ServerInfo@index');
Route::get('rest/api/latest/serverInfo', 'app\jira\controller\v2\ServerInfo@index');
Route::get('rest/api/3/serverInfo', 'app\jira\controller\v2\ServerInfo@index');

Route::get('rest/api/3/field', 'app\jira\controller\v3\Meta@fields')->middleware(JiraAuth::class);
Route::get('rest/api/3/resolution', 'app\jira\controller\v3\Meta@resolutions')->middleware(JiraAuth::class);

$issueKeyPattern = ['issueIdOrKey' => '[A-Za-z][A-Za-z0-9_]*-\d+|\d+'];

Route::group('rest/api/3', function () use ($issueKeyPattern) {
    Route::get('myself', 'app\jira\controller\v3\Myself@index');
    Route::get('user/search', 'app\jira\controller\v3\User@index');
    Route::get('user', 'app\jira\controller\v3\User@index');
    Route::get('project/search', 'app\jira\controller\v3\Project@search');
    Route::post('project', 'app\jira\controller\v3\Project@create');
    Route::get('project/:projectKey/versions', 'app\jira\controller\v3\Project@versions');
    Route::get('project/:projectKey/components', 'app\jira\controller\v3\Project@components');
    Route::get('project/:projectKey', 'app\jira\controller\v3\Project@read');
    Route::post('version', 'app\jira\controller\v3\Version@create');
    Route::post('component', 'app\jira\controller\v3\Component@create');
    Route::post('search', 'app\jira\controller\v3\Search@index');

    Route::get('issue/:issueIdOrKey/comment', 'app\jira\controller\v3\IssueComment@index')
        ->pattern($issueKeyPattern);
    Route::post('issue/:issueIdOrKey/comment', 'app\jira\controller\v3\IssueComment@create')
        ->pattern($issueKeyPattern);
    Route::get('issue/:issueIdOrKey/worklog', 'app\jira\controller\v3\IssueWorklog@index')
        ->pattern($issueKeyPattern);
    Route::post('issue/:issueIdOrKey/worklog', 'app\jira\controller\v3\IssueWorklog@create')
        ->pattern($issueKeyPattern);
    Route::post('issue/:issueIdOrKey/attachments', 'app\jira\controller\v3\IssueAttachment@create')
        ->pattern($issueKeyPattern);
    Route::get('issue/:issueIdOrKey/transitions', 'app\jira\controller\v3\IssueTransition@index')
        ->pattern($issueKeyPattern);
    Route::post('issue/:issueIdOrKey/transitions', 'app\jira\controller\v3\IssueTransition@apply')
        ->pattern($issueKeyPattern);
    Route::get('issue/:issueIdOrKey/changelog', 'app\jira\controller\v3\IssueChangelog@index')
        ->pattern($issueKeyPattern);
    Route::get('issue/:issueIdOrKey/watchers', 'app\jira\controller\v3\IssueWatcher@index')
        ->pattern($issueKeyPattern);
    Route::post('issue/:issueIdOrKey/watchers', 'app\jira\controller\v3\IssueWatcher@addWatcher')
        ->pattern($issueKeyPattern);
    Route::delete('issue/:issueIdOrKey/watchers', 'app\jira\controller\v3\IssueWatcher@removeWatcher')
        ->pattern($issueKeyPattern);

    Route::post('issueLink', 'app\jira\controller\v3\IssueLink@create');
    Route::delete('issueLink/:linkId', 'app\jira\controller\v3\IssueLink@delete');

    Route::post('filter', 'app\jira\controller\v3\Filter@create');
    Route::get('filter/search', 'app\jira\controller\v3\Filter@search');
    Route::get('filter/:filterId', 'app\jira\controller\v3\Filter@read');
    Route::put('filter/:filterId', 'app\jira\controller\v3\Filter@update');
    Route::delete('filter/:filterId', 'app\jira\controller\v3\Filter@delete');

    Route::post('issue', 'app\jira\controller\v3\Issue@create');
    Route::get('issue/:issueIdOrKey', 'app\jira\controller\v3\Issue@read')
        ->pattern($issueKeyPattern);
    Route::put('issue/:issueIdOrKey', 'app\jira\controller\v3\Issue@update')
        ->pattern($issueKeyPattern);
    Route::delete('issue/:issueIdOrKey', 'app\jira\controller\v3\Issue@delete')
        ->pattern($issueKeyPattern);
})->middleware(JiraAuth::class);

Route::group('rest/webhooks/1.0', function () {
    Route::get('webhook', 'app\jira\controller\webhook\Webhook@index');
    Route::post('webhook', 'app\jira\controller\webhook\Webhook@create');
    Route::delete('webhook/:webhookId', 'app\jira\controller\webhook\Webhook@delete');
})->middleware(JiraAuth::class);
