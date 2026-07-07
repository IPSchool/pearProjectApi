<?php

namespace app\project\controller;

use app\common\Model\CommonModel;
use app\common\Model\Member;
use app\common\Model\Project;
use app\common\Model\ProjectLog;
use app\common\Model\ProjectLogReaction;
use app\jira\service\JiraIssueService;
use app\common\Model\TaskMember;
use app\common\Model\TaskTag;
use app\common\Model\TaskToTag;
use app\common\Model\TaskWorkTime;
use controller\BasicApi;
use Exception;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\facade\Request;
use service\TaskResolutionService;

/**
 */
class Task extends BasicApi
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\Task();
        }
    }

    /**
     * 显示资源列表
     * @return void
     * @throws DbException
     */
    public function index()
    {
        $where = [];
        $params = request_only('stageCode,pcode,keyword,order,projectCode,deleted');
        foreach (['stageCode', 'pcode', 'deleted', 'projectCode'] as $key) {
            if ($key == 'projectCode') {
                if (isset($params[$key]) && $params[$key] !== '') {
                    $project = Project::resolveByRef($params[$key]);
                    if ($project) {
                        $where[] = ['project_code', '=', $project['code']];
                    } else {
                        $where[] = ['project_code', '=', $params[$key]];
                    }
                }
                continue;
            }
            (isset($params[$key]) && $params[$key] !== '') && $where[] = [$key, '=', $params[$key]];
        }
        if (isset($params['keyword'])) {
            $where[] = ['name', 'like', "%{$params['keyword']}%"];
        }
        $order = 'sort asc,id asc';
        if (isset($params['order'])) {
            $order = $params['order'];
        }
        $list = $this->model->_list($where, $order);
        $memberCode = trim((string) Request::post('memberCode', ''));
        if ($memberCode !== '' && !empty($list['list'])) {
            $taskCodes = array_column($list['list'], 'code');
            $participantTaskCodes = TaskMember::where('member_code', $memberCode)
                ->whereIn('task_code', $taskCodes)
                ->column('task_code');
            $participantSet = array_flip($participantTaskCodes);
            $filtered = [];
            foreach ($list['list'] as $task) {
                if (
                    ($task['assign_to'] ?? '') === $memberCode
                    || ($task['create_by'] ?? '') === $memberCode
                    || isset($participantSet[$task['code']])
                ) {
                    $filtered[] = $task;
                }
            }
            $list['list'] = $filtered;
            $list['total'] = count($filtered);
        }
        if ($list['list']) {
            foreach ($list['list'] as &$task) {
                if ($task instanceof \think\Model) {
                    $task = $task->toArray();
                }
                $task['executor'] = Member::where(['code' => $task['assign_to']])->field('name,avatar,code')->find();
                if (!empty($task['create_by'])) {
                    $task['creator'] = Member::where(['code' => $task['create_by']])->field('name,avatar,code')->find();
                } else {
                    $task['creator'] = null;
                }
                TaskResolutionService::normalizeTaskForApi($task);
            }
            unset($task);
        }
        $this->success('', $list);
    }

    /**
     * 项目时间段任务统计
     */
    public function dateTotalForProject()
    {
        $projectCode = Request::post('projectCode');
        $list = $this->model->dateTotalForProject($projectCode);
        $this->success('', $list);
    }

    /**
     * 获取自己的任务
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function selfList()
    {
        $taskType = Request::post('taskType', 1);
        $type = Request::post('type', 0);
        $memberCode = Request::post('memberCode', '');
        if (!$memberCode) {
            $member = getCurrentMember();
        } else {
            $member = Member::where(['code' => $memberCode])->find();
        }
        $done = 1;
        if (!$type) {
            $done = 0;
        }
        $type == -1 && $done = $type;
        $list = $this->model->getMemberTasks($member['code'], $done, $taskType, Request::post('page'), Request::post('pageSize'));
        $priLabels = [0 => '普通', 1 => '紧急', 2 => '非常紧急'];
        $statusLabels = [0 => '未开始', 1 => '已完成', 2 => '进行中', 3 => '挂起', 4 => '测试中'];
        if ($list['list']) {
            foreach ($list['list'] as &$task) {
                $taskInfo = \app\common\Model\Task::find($task['id']);
                $task['parentDone'] = $taskInfo['parentDone'];
                $task['hasUnDone'] = $taskInfo['hasUnDone'];
                $task['priText'] = $priLabels[$task['pri'] ?? 0] ?? '普通';
                $task['statusText'] = $statusLabels[$task['status'] ?? 0] ?? '未开始';
                $task['executor'] = Member::where(['code' => $task['assign_to']])->field('name,avatar,code')->find();
                $task['projectInfo'] = [
                    'id'   => (int) ($task['project_id'] ?? 0),
                    'code' => $task['project_code'],
                    'name' => $task['projectName'] ?? '',
                ];
                if (!empty($task['project_open_prefix']) && !empty($task['project_prefix']) && !empty($task['id_num'])) {
                    $task['issueKey'] = strtoupper($task['project_prefix']) . '-' . $task['id_num'];
                } else {
                    $task['issueKey'] = '';
                }
                unset($task['project_prefix'], $task['project_open_prefix']);
            }
        }
        $this->success('', $list);
    }

    public function taskSources()
    {
        $code = Request::post('taskCode');
        try {
            $list = $this->model->taskSources($code);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('', $list);
    }

    public function getListByTaskTag()
    {
        $taskTagCode = Request::param('taskTagCode');
        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', cookie('pageSize'));
        $prefix = config('database.prefix');
        $sql = "select *,t.id as id,t.code as code from {$prefix}task_to_tag as tt join {$prefix}task as t on tt.task_code = t.code where tt.tag_code = '{$taskTagCode}' order by t.id desc";
        $list = CommonModel::limitByQuery($sql, $page, $pageSize);
        if ($list['list']) {
            foreach ($list['list'] as &$task) {
                $task['tags'] = TaskToTag::where(['task_code' => $task['code']])->withoutField('id')->order('id asc')->select()->toArray();
                $task['executor'] = null;
                if ($task['assign_to']) {
                    $task['executor'] = Member::where(['code' => $task['assign_to']])->field('name,code,avatar')->find();
                }
            }
        }
        $this->success('', $list);
    }

    public function read(Request $request)
    {
        //todo 隐私模式阅读权限
        $data = request_only('taskCode');
        try {
            $result = $this->model->read($data['taskCode']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('', $result);
        }
    }

    /**
     * 按 Jira Issue Key 读取任务（如 KAN-1）
     */
    public function readByIssueKey(Request $request)
    {
        $issueKey = trim((string) $request::post('issueKey', ''));
        if ($issueKey === '') {
            $this->error('请提供 Issue Key');
        }
        $parsed = JiraIssueService::parseIssueKey($issueKey);
        if (!$parsed) {
            $this->error('Issue 不存在', 404);
        }
        try {
            $result = $this->model->read($parsed['task']['code']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $result['issueKey'] = $parsed['key'];
            $this->success('', $result);
        }
    }

    /**
     * 按项目引用 + 任务序号/id/code 读取（URL 友好）
     * projectRef: 项目数字 id / prefix / code
     * taskRef: id_num / 全局 id / code / ISSUE-KEY
     */
    public function readByRef(Request $request)
    {
        $projectRef = trim((string) $request::post('projectRef', ''));
        $taskRef = trim((string) $request::post('taskRef', ''));
        if ($projectRef === '' || $taskRef === '') {
            $this->error('请提供项目和任务');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*-\d+$/', $taskRef)) {
            $parsed = JiraIssueService::parseIssueKey($taskRef);
            if (!$parsed) {
                $this->error('Issue 不存在', 404);
            }
            try {
                $result = $this->model->read($parsed['task']['code']);
            } catch (Exception $e) {
                $this->error($e->getMessage(), $e->getCode());
            }
            if ($result) {
                $result['issueKey'] = $parsed['key'];
                $this->success('', $result);
            }
            return;
        }

        $project = Project::resolveByRef($projectRef);
        if (!$project) {
            $this->error('项目不存在', 404);
        }
        $project = $project->toArray();

        $task = null;
        if (ctype_digit($taskRef)) {
            $num = (int) $taskRef;
            $task = $this->model->where([
                'project_code' => $project['code'],
                'id_num'       => $num,
                'deleted'      => 0,
            ])->field('code')->find();
            if (!$task) {
                $task = $this->model->where([
                    'project_code' => $project['code'],
                    'id'           => $num,
                    'deleted'      => 0,
                ])->field('code')->find();
            }
        } else {
            $task = $this->model->where([
                'project_code' => $project['code'],
                'code'         => $taskRef,
                'deleted'      => 0,
            ])->field('code')->find();
        }

        if (!$task) {
            $this->error('任务不存在', 404);
        }

        try {
            $result = $this->model->read($task['code']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('', $result);
        }
    }

    /**
     * 新增
     */
    public function save(Request $request)
    {
        $data = request_only('name,stage_code,project_code,assign_to,pcode');
        if (!isset($data['assign_to'])) {
            $data['assign_to'] = '';
        }
        if (!isset($data['pcode'])) {
            $data['pcode'] = '';
        }
        if (!$request::post('name')) {
            $this->error("请填写任务标题");
        }
        $member = getCurrentMember();
        if ($data['pcode']) {
            $parentTask = $this->model->where(['code' => $data['pcode']])->find();
            if (!$parentTask) {
                $this->error('父任务无效', 5);
            }
            if ($parentTask['deleted']) {
                $this->error('父任务在回收站中无法编辑', 6);
            }
            $data['project_code'] = $parentTask['project_code'];
            $data['stage_code'] = $parentTask['stage_code'];
        } elseif (!empty($data['project_code'])) {
            $project = Project::resolveByRef($data['project_code']);
            if (!$project) {
                $this->error('该项目已失效');
            }
            $data['project_code'] = $project['code'];
        }
        $result = $this->model->createTask($data['stage_code'], $data['project_code'], $data['name'], $member['code'], $data['assign_to'], $data['pcode']);
        if (!isError($result)) {
            $this->success('', $result);
        }
        $this->error($result['msg']);
    }

    /**
     * 执行任务
     * @param Request $request
     */
    public function taskDone(Request $request)
    {
        $data = request_only('taskCode,done,resolution');
        if (!$request::post('taskCode')) {
            $this->error("请选择任务");
        }
        try {
            $result = $this->model->taskDone(
                $data['taskCode'],
                $data['done'],
                $data['resolution'] ?? null
            );
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 指派任务
     * @param Request $request
     */
    public function assignTask(Request $request)
    {
        $data = request_only('taskCode,executorCode');
        if (!$request::post('taskCode')) {
            $this->error("请选择任务");
        }
        try {
            $result = $this->model->assignTask($data['taskCode'], $data['executorCode']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }


    /**
     * 批量
     * 指派任务
     * @param Request $request
     */
    public function batchAssignTask(Request $request)
    {
        $taskCodes = $request::param('taskCodes');
        $executorCode = $request::param('executorCode');
        if ($taskCodes) {
            $result = $this->model->batchAssignTask(json_decode($taskCodes), $executorCode);
            if (isError($result)) {
                $this->error($result['msg'], $result['errno']);
            }
        }
        $this->success();
    }

    /**
     * 排序
     * @param Request $request
     */
    public function sort(Request $request)
    {
        $data = request_only('preTaskCode,nextTaskCode,toStageCode');
        if (!$request::post('preTaskCode')) {
            $this->error("参数有误");
        }
        try {
            $this->model->sort($data['preTaskCode'], $data['nextTaskCode'], $data['toStageCode']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success();
    }

    /**
     * 发表评论
     * @param Request $request
     */
    public function createComment(Request $request)
    {
        $data = request_only('taskCode,comment,mentions');
        if (!$request::post('taskCode')) {
            $this->error("请选择任务");
        }
        if (isset($data['mentions'])) {
            $data['mentions'] = json_decode($data['mentions']);
        }
        try {
            $result = $this->model->createComment($data['taskCode'], $data['comment'], $data['mentions']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 删除评论
     */
    public function deleteComment(Request $request)
    {
        $logCode = $request::post('logCode');
        if (!$logCode) {
            $this->error('请选择评论');
        }
        try {
            $result = $this->model->deleteComment($logCode);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success();
        }
        $this->error('删除失败');
    }

    /**
     * 评论点赞 / 表情
     */
    public function toggleCommentReaction(Request $request)
    {
        $logCode = $request::post('logCode');
        $reaction = $request::post('reaction', 'like');
        if (!$logCode) {
            $this->error('请选择评论');
        }
        $log = ProjectLog::where(['code' => $logCode, 'is_comment' => 1])->find();
        if (!$log) {
            $this->error('评论不存在');
        }
        try {
            ProjectLogReaction::toggle($logCode, getCurrentMember()['code'], $reaction);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        $reactions = ProjectLogReaction::summarizeForLogs([$logCode], getCurrentMember()['code']);
        $this->success('', ['reactions' => $reactions[$logCode] ?? []]);
    }

    /**
     * 保存
     * @param Request $request
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function edit(Request $request)
    {
        // 局部更新：只写入请求里实际提交的字段，避免 request_only 空默认值清空 description 等
        $data = request_present_only('name,sort,end_time,begin_time,pri,description,work_time,status,resolution');
        $code = $request::post('taskCode');
        if (!$code) {
            $this->error("请选择一个任务");
        }
        if (!$data) {
            $this->error("没有可更新的内容");
        }
        $template = $this->model->where(['code' => $code])->field('id')->find();
        if (!$template) {
            $this->error("该任务已失效");
        }
        try {
            $result = $this->model->edit($code, $data);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;

        }
        if ($result) {
            $this->success();
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 设置或清除任务父项
     */
    public function setParent(Request $request)
    {
        $code = $request::post('taskCode');
        if (!$code) {
            $this->error("请选择一个任务");
        }
        $parentCode = $request::post('parentCode', '');
        try {
            $result = $this->model->setParent($code, $parentCode);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success();
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 设置隐私模式
     * @param Request $request
     * @throws Exception
     */
    public function setPrivate(Request $request)
    {
        $private = intval($request::post('private', 0));
        $code = $request::post('taskCode');
        if ($private === 0 || $private === 1) {
            $result = $this->model->edit($code, ['private' => $private]);
            if ($result) {
                $this->success();
            }
            $this->error("操作失败，请稍候再试！");
        }
        $this->success();
    }

    /**
     * 点赞
     * @param Request $request
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function like(Request $request)
    {
        $data = request_only('like');
        $code = $request::post('taskCode');
        if (!$code) {
            $this->error("请选择一个任务");
        }
        $template = $this->model->where(['code' => $code])->field('id')->find();
        if (!$template) {
            $this->error("该任务已失效");
        }
        try {
            $result = $this->model->like($code, $data['like']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;

        }
        if ($result) {
            $this->success();
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 任务标签列表
     * @param Request $request
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function taskToTags(Request $request)
    {
        $taskCode = $request::param('taskCode');
        $tags = TaskToTag::where(['task_code' => $taskCode])->withoutField('id')->select()->toArray();
        $this->success('', $tags);
    }

    /**
     * 设置标签
     * @param Request $request
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function setTag(Request $request)
    {
        $tagCode = $request::param('tagCode');
        $taskCode = $request::param('taskCode');
        if (!$taskCode) {
            $this->error("请选择一个任务");
        }
        if (!$tagCode) {
            $this->error("请选择一个标签");
        }
        TaskTag::setTag($tagCode, $taskCode);
        $this->success();
    }

    /**
     * 收藏
     * @param Request $request
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function star(Request $request)
    {
        $data = request_only('star');
        $code = $request::post('taskCode');
        if (!$code) {
            $this->error("请选择一个任务");
        }
        $task = $this->model->where(['code' => $code])->field('id')->find();
        if (!$task) {
            $this->notFound();
        }
        try {
            $result = $this->model->star($code, $data['star']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;

        }
        if ($result) {
            $this->success();
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * @throws DbException
     */
    public function taskLog()
    {
        $taskCode = Request::post('taskCode');
        $showAll = Request::post('all', 0);
        $onlyComment = Request::post('comment', 0);
        $where = [];
        $where[] = ['source_code', '=', $taskCode];
        $where[] = ['action_type', '=', 'task'];
        if ($onlyComment) {
            $where[] = ['is_comment', '=', 1];
        }
        $projectLogModel = new ProjectLog();
        if ($showAll) {
            $list = [];
            $list['list'] = $projectLogModel->where($where)->order('id asc')->select()->toArray();
            $list['total'] = count($list['list']);
        } else {
            $list = $projectLogModel->_list($where, 'id desc');
            if ($list['list']) {
                $list['list'] = array_reverse($list['list']);
            }
        }
        if ($list['list']) {
            $logCodes = array_column($list['list'], 'code');
            $memberCode = getCurrentMember()['code'];
            $reactionMap = [];
            try {
                $reactionMap = ProjectLogReaction::summarizeForLogs($logCodes, $memberCode);
            } catch (\Throwable $e) {
                $reactionMap = [];
            }
            foreach ($list['list'] as &$item) {
                if ($item['is_robot'] && $item['type'] != 'claim') {
                    $item['member'] = ['name' => 'PP Robot'];
                    continue;
                }
                $member = Member::where(['code' => $item['member_code']])->field('id,name,avatar,code')->find();
                !$member && $member = [];
                $item['member'] = $member;
                $item['member_name'] = $member['name'] ?? '';
                $item['member_avatar'] = $member['avatar'] ?? '';
                $item['reactions'] = $reactionMap[$item['code']] ?? [];
            }
        }
        $this->success('', $list);
    }

    /**
     * 工时间录
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function _taskWorkTimeList()
    {
        $taskCode = Request::param('taskCode');
        $workTimeList = TaskWorkTime::where(['task_code' => $taskCode])->select()->toArray();
        if ($workTimeList) {
            foreach ($workTimeList as &$workTime) {
                $member = Member::where(['code' => $workTime['member_code']])->field('avatar,name')->find();
                $workTime['member'] = $member;
            }
        }
        $this->success('', $workTimeList);
    }

    /**
     * 记录工时
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function saveTaskWorkTime()
    {
        $param = request_only('beginTime,num,content,taskCode');
        $result = TaskWorkTime::createData($param['taskCode'], getCurrentMember()['code'], $param['num'], $param['beginTime'], $param['content']);
        if (isError($result)) {
            $this->error($result['msg'], $result['errno']);
        }
        $this->success();
    }

    /**
     * 修改工时
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function editTaskWorkTime()
    {
        $param = request_only('beginTime,num,content');
        $code = Request::param('code');
        if ($code) {
            $workTime = TaskWorkTime::where(['code' => $code])->find();
            if (!$workTime) {
                return error(1, '该记录已失效');
            }
        }
        if (isset($param['beginTime'])) {
            $param['begin_time'] = $param['beginTime'];
            unset($param['beginTime']);
        }
        $result = TaskWorkTime::update($param, ['code' => $code]);
        $this->success();
    }

    /**
     * 删除工时
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function delTaskWorkTime()
    {
        $code = Request::param('code');
        if ($code) {
            $workTime = TaskWorkTime::where(['code' => $code])->find();
            if (!$workTime) {
                return error(1, '该记录已失效');
            }
        }
        $result = TaskWorkTime::destroy(['code' => $code]);
        $this->success();
    }


    /**
     * 下载导入任务模板
     */
    public function _downloadTemplate()
    {
        return download(gateb_root_path() . 'data/template/importTask.xlsx', '批量导入任务模板.xlsx');
    }

    /**
     * 上传文件
     */
    public function uploadFile()
    {
        $projectCode = Request::param('projectCode');
        $count = $this->model->uploadFile(Request::file('file'), $projectCode, getCurrentMember()['code']);
        if (isError($count)) {
            $this->error($count['msg']);
        }
        $this->success('', $count);
    }

    /**
     * 批量放入回收站
     */
    public function recycleBatch()
    {
        try {
            $this->model->recycleBatch(Request::post('stageCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

    /**
     * 放入回收站
     */
    public function recycle()
    {
        try {
            $this->model->recycle(Request::post('taskCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

    /**
     * 恢复
     */
    public function recovery()
    {
        try {
            $this->model->recovery(Request::post('taskCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

    /**
     * 彻底删除
     */
    public function delete()
    {
        try {
            $this->model->del(Request::post('taskCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }
}
