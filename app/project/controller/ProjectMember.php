<?php

namespace app\project\controller;

use app\common\Model\InviteLink;
use app\common\Model\Member;
use app\common\Model\MemberAccount;
use app\common\Model\Organization;
use app\common\Model\Project;
use controller\BasicApi;
use service\MailService;
use think\facade\Request;

/**
 * 项目成员
 */
class ProjectMember extends BasicApi
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\ProjectMember();
        }
    }

    private function resolveProjectCode(string $ref): ?string
    {
        $project = Project::resolveByRef($ref);
        return $project ? $project['code'] : null;
    }

    /**
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $projectCode = $this->resolveProjectCode(trim(Request::post('projectCode', '')));
        if (!$projectCode) {
            $this->error('请先选择项目');
        }
        $where = [];
        $where[] = ['project_code', '=', $projectCode];
        $list = $this->model->_list($where, 'is_owner desc');
        if ($list['list']) {
            foreach ($list['list'] as &$item) {
                if ($item instanceof \think\Model) {
                    $item = $item->toArray();
                }
                $member = Member::where(['code' => $item['member_code']])->field('name,avatar,code,email')->find();
                !$member && $member = [];
                $member['is_owner'] = $item['is_owner'];
                $item = $member;
            }
            unset($item);
        }
        $this->success('', $list);
    }

    public function _listForInvite()
    {
        $code = $this->resolveProjectCode(trim(Request::post('projectCode', '')));
        if (!$code) {
            $this->error('请先选择项目');
        }
        $orgCode = getCurrentOrganizationCode();
        $memberAccountList = MemberAccount::where([['organization_code', '=', $orgCode]])->select()->toArray();
        $list = [];
        if ($memberAccountList) {
            foreach ($memberAccountList as $member) {
                $has = $this->model->where('member_code', $member['member_code'])->where('project_code', $code)->field('id')->find();
                $item['memberCode'] = $member['member_code'];
                $item['status'] = $member['status'];
                $item['avatar'] = $member['avatar'];
                $item['name'] = $member['name'];
                $item['email'] = $member['email'] ?? '未绑定邮箱';
                $item['joined'] = (bool) $has;
                $list[] = $item;
            }
        }
        $this->success('', $list);
    }

    /**
     * 邀请成员查询
     */
    public function searchInviteMember()
    {
        $keyword = trim(Request::post('keyword'));
        $code = $this->resolveProjectCode(trim(Request::post('projectCode', '')));
        if (!$code) {
            $this->error('请先选择项目');
        }
        if (!$keyword) {
            $this->success('', []);
        }
        $orgCode = getCurrentOrganizationCode();
        $projectMemberIds = $this->model->where([['project_code', '=', $code]])->column('member_code');
        $tempList = [];
        $memberAccountList = MemberAccount::where([
            ['name', 'like', "%{$keyword}%"],
            ['organization_code', '=', $orgCode],
        ])->select()->toArray();
        if ($memberAccountList) {
            foreach ($memberAccountList as $member) {
                $item = [];
                $item['memberCode'] = $member['member_code'];
                $item['status'] = $member['status'];
                $item['avatar'] = $member['avatar'];
                $item['name'] = $member['name'];
                $item['email'] = $member['email'] ?? '未绑定邮箱';
                $item['joined'] = in_array($member['member_code'], $projectMemberIds);
                $tempList[$item['memberCode']] = $item;
            }
        }
        $memberList = Member::where([['email', 'like', "%{$keyword}%"]])->select()->toArray();
        if ($memberList) {
            foreach ($memberList as $member) {
                $item = [];
                $item['memberCode'] = $member['code'];
                $item['status'] = 1;
                $item['avatar'] = $member['avatar'];
                $item['name'] = $member['name'];
                $item['email'] = $member['email'] ?? '未绑定邮箱';
                $item['joined'] = in_array($item['memberCode'], $projectMemberIds);
                $tempList[$item['memberCode']] = $item;
            }
        }
        $this->success('', array_values($tempList));
    }

    /**
     * 通过邀请连接邀请成员
     */
    public function _joinByInviteLink()
    {
        $inviteCode = Request::param('inviteCode');
        $inviteLink = InviteLink::where(['code' => $inviteCode])->find();
        if (!$inviteLink || nowTime() >= $inviteLink['over_time']) {
            $this->error('该链接已失效');
        }
        $project = null;
        if ($inviteLink['invite_type'] == 'project') {
            $project = Project::where(['code' => $inviteLink['source_code']])->find();
            if (!$project) {
                $this->error('该项目已失效');
            }
            try {
                $this->model->inviteMember(getCurrentMember()['code'], $project['code']);
            } catch (\Exception $e) {
                $this->error($e->getMessage(), $e->getCode());
            }
        }
        $currentOrganization = null;
        $list = MemberAccount::where(['member_code' => getCurrentMember()['code']])->order('id asc')->select()->toArray();
        $organizationList = [];
        if ($list) {
            foreach ($list as $item) {
                $organization = Organization::where(['code' => $item['organization_code']])->find();
                if ($organization) {
                    $organizationList[] = $organization;
                }
                if ($project && $item['organization_code'] == $project['organization_code']) {
                    $currentOrganization = $organization;
                }
            }
        }
        $this->success('', ['organizationList' => $organizationList, 'currentOrganization' => $currentOrganization]);
    }

    /**
     * 邀请成员
     */
    public function inviteMember()
    {
        $data = request_only('memberCode,projectCode');
        $projectCode = $this->resolveProjectCode(trim($data['projectCode'] ?? ''));
        if (!$data['memberCode'] || !$projectCode) {
            $this->error('数据异常');
        }
        try {
            $this->model->inviteMember($data['memberCode'], $projectCode);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('');
    }

    /**
     * 发送项目邀请邮件（外部邮箱）
     */
    public function sendInviteEmail()
    {
        $email = trim(Request::post('email', ''));
        $projectRef = trim(Request::post('projectCode', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('请填写有效邮箱');
        }
        $project = Project::resolveByRef($projectRef);
        if (!$project) {
            $this->error('该项目已失效');
        }
        try {
            $link = InviteLink::createInvite('project', $project['code']);
            $inviteUrl = MailService::buildInviteUrl($link['code']);
            $inviter = getCurrentMember()['name'] ?? '项目成员';
            MailService::send(
                $email,
                $email,
                '邀请你加入项目「' . $project['name'] . '」',
                MailService::inviteProjectBody($project['name'], $inviter, $inviteUrl)
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode() ?: 1);
        }
        $this->success('邀请邮件已发送', ['inviteUrl' => $inviteUrl ?? '']);
    }

    /**
     * 移除成员
     */
    public function removeMember()
    {
        $data = request_only('memberCode,projectCode');
        $projectCode = $this->resolveProjectCode(trim($data['projectCode'] ?? ''));
        if (!$data['memberCode'] || !$projectCode) {
            $this->error('数据异常');
        }
        try {
            $this->model->removeMember($data['memberCode'], $projectCode);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('');
    }
}
