#!/usr/bin/env php
<?php
/**
 * Gate B fixture — jira-test user + TST project (idempotent).
 */
namespace think;

$rootPath = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR;
require $rootPath . 'thinkphp/base.php';
Loader::addAutoLoadDir($rootPath . 'extend');

Container::get('app', [$rootPath . 'application/'])->initialize();

use app\common\Model\JiraApiToken;
use app\common\Model\Member;
use app\common\Model\MemberAccount;
use app\common\Model\Organization;
use app\common\Model\Project;
use app\common\Model\ProjectMember;
use app\common\Model\SystemConfig;
use app\common\Model\TaskStages;
use think\Db;

$email = config('jira.gate_b_email');
$projectKey = config('jira.default_project_key');
$projectName = 'Test Project';

echo "[GateB] fixture-init: email={$email}, project={$projectKey}\n";

$migration = $rootPath . 'data/2.9.0/2.8.17-2.9.0-jira.sql';
if (is_file($migration)) {
    $sql = file_get_contents($migration);
    Db::execute($sql);
    echo "[GateB] Applied jira schema migration\n";
}

$member = Member::where(['email' => $email])->find();
if (!$member) {
    $member = Member::createMember([
        'email'    => $email,
        'name'     => 'Jira Gate B Test',
        'realname' => 'Jira Gate B Test',
        'account'  => 'jira-gate-b',
        'password' => md5('unused-jira-api-token-auth'),
        'status'   => 1,
    ]);
    echo "[GateB] Created member id={$member['id']} code={$member['code']}\n";
} else {
    echo "[GateB] Member exists id={$member['id']} code={$member['code']}\n";
}

$config = (new SystemConfig())->info();
$orgCode = $config['single_org_code'] ?? '';
if (!$orgCode) {
    $org = Organization::order('id asc')->find();
    $orgCode = $org ? $org['code'] : '';
}
if (!$orgCode) {
    fwrite(STDERR, "[GateB] ERROR: no organization found\n");
    exit(1);
}

$account = MemberAccount::where([
    'member_code'        => $member['code'],
    'organization_code'  => $orgCode,
])->find();
if (!$account) {
    MemberAccount::inviteMember($member['code'], $orgCode);
    echo "[GateB] Linked member to org {$orgCode}\n";
}

$project = Project::where([
    'prefix'      => $projectKey,
    'open_prefix' => 1,
    'deleted'     => 0,
])->find();

if (!$project) {
    $project = Project::create([
        'create_time'         => nowTime(),
        'code'                => createUniqueCode('project'),
        'name'                => $projectName,
        'description'         => 'Gate B Jira API test project',
        'organization_code'   => $orgCode,
        'prefix'              => $projectKey,
        'open_prefix'         => 1,
        'private'             => 0,
        'access_control_type' => 'open',
        'task_board_theme'    => 'default',
        'cover'               => 'http://easyproject.net/static/image/default/project-cover.png',
    ]);
    echo "[GateB] Created project id={$project['id']} code={$project['code']} key={$projectKey}\n";

    foreach ([['name' => 'To Do'], ['name' => 'In Progress'], ['name' => 'Done']] as $sort => $stage) {
        TaskStages::create([
            'project_code' => $project['code'],
            'name'         => $stage['name'],
            'sort'         => $sort,
            'code'         => createUniqueCode('taskStages'),
            'create_time'  => nowTime(),
        ]);
    }
    echo "[GateB] Created default task stages\n";
} else {
    echo "[GateB] Project exists id={$project['id']} key={$projectKey}\n";
}

$pm = ProjectMember::where([
    'project_code' => $project['code'],
    'member_code'  => $member['code'],
])->find();
if (!$pm) {
    ProjectMember::create([
        'member_code'  => $member['code'],
        'project_code' => $project['code'],
        'is_owner'     => 1,
        'join_time'    => nowTime(),
    ]);
    echo "[GateB] Added member to project\n";
}

try {
    JiraApiToken::upsertForMember(
        $member['code'],
        config('jira.gate_b_account_id'),
        config('jira.gate_b_token'),
        'gate-b-test'
    );
    echo "[GateB] Upserted Jira API token for {$email}\n";
} catch (\Throwable $e) {
    echo "[GateB] WARN: token upsert skipped — {$e->getMessage()}\n";
}

echo "[GateB] fixture-init done.\n";
