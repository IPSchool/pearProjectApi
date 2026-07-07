<?php

namespace app\project\controller;

use controller\BasicApi;
use app\common\Model\Project;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\facade\Request;

/**
 */
class ProjectFeatures extends BasicApi
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\ProjectFeatures();
        }
    }

    /**
     * 显示资源版本库
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function index()
    {
        $where = [];
        $ref = Request::post('projectCode');
        if (!$ref) {
            $this->error("请选择一个项目");
        }
        $project = Project::resolveByRef($ref);
        if (!$project) {
            $this->error('该项目已失效');
        }
        $where[] = ['project_code', '=', $project['code']];
//        $list = $this->model->_list($where, 'sort asc,id asc');
        $list = $this->model->where($where)->order('id desc')->select()->toArray();
        $this->success('', $list);
    }

    /**
     * 新增
     * @param Request $request
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function save(Request $request)
    {
        $data = request_only('name,projectCode,description');
        if (!$request::post('name')) {
            $this->error("请填写版本库名称");
        }
        $project = Project::resolveByRef($data['projectCode'] ?? '');
        if (!$project) {
            $this->error('该项目已失效');
        }
        $result = $this->model->createData(
            $data['name'],
            $data['description'],
            $project['code'],
            getCurrentOrganizationCode()
        );
        if (!isError($result)) {
            $this->success('添加成功', $result);
        }
        $this->error($result['msg']);
    }

    /**
     * 确保项目存在默认里程碑分组（Legacy features，UI 不再单独暴露）
     */
    public function ensureDefault()
    {
        $ref = Request::post('projectCode');
        if (!$ref) {
            $this->error('请选择一个项目');
        }
        $project = Project::resolveByRef($ref);
        if (!$project) {
            $this->error('该项目已失效');
        }
        $existing = $this->model->where(['project_code' => $project['code']])->order('id asc')->find();
        if ($existing) {
            $this->success('', $existing->toArray());
            return;
        }
        $result = $this->model->createData('里程碑', '', $project['code'], getCurrentOrganizationCode());
        if (!isError($result)) {
            $this->success('', $result);
            return;
        }
        $this->error($result['msg']);
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
        $data = request_only('name,description,featuresCode');
        if (!$request::post('name')) {
            $this->error("请填写版本库名称");
        }
        if (!$data['featuresCode']) {
            $this->error("请选择一个版本库");
        }
        $features = $this->model->where(['code' => $data['featuresCode']])->field('id,project_code')->find();
        if (!$features) {
            $this->error("该版本库已失效");
        }
        $has = $this->model->where(['name' => $data['name'], 'project_code' => $features['project_code']])->field('id')->find();
        if ($has && $has['id'] != $features['id']) {
            $this->error("该版本库名称已存在");
        }
        $result = $this->model->_edit(['name' => $data['name'], 'description' => $data['description']], ['code' => $data['featuresCode']]);
        if ($result) {
            $this->success('');
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 删除版本库
     * @return void
     * @throws Exception
     * @throws PDOException
     */
    public function delete()
    {
        $code = Request::post('featuresCode');
        if (!$code) {
            $this->error("请选择一个版本库");
        }
        $result = $this->model->deleteProjectFeatures($code);
        if (isError($result)) {
            $this->error($result['msg'], $result['errno']);
        }
        $this->success();
    }
}
