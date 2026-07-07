<?php

namespace app\common\Model;


use service\DateService;

/**
 * 邀请链接
 */
class InviteLink extends CommonModel
{
    protected $append = [];

    /**
     * @param $inviteType
     * @param $sourceCode
     * @return InviteLink
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function createInvite($inviteType, $sourceCode)
    {
        $canonicalCode = self::resolveSourceCode($inviteType, $sourceCode);
        $memberCode = getCurrentMember()['code'];
        $inviteLink = self::where([
            'invite_type' => $inviteType,
            'source_code' => $canonicalCode,
            'create_by'   => $memberCode,
        ])->find();
        if ($inviteLink && nowTime() >= $inviteLink['over_time']) {
            $inviteLink->delete();
            $inviteLink = null;
        }
        if (!$inviteLink) {
            $fileData = [
                'code'        => createUniqueCode('inviteLink'),
                'create_by'   => $memberCode,
                'invite_type' => $inviteType,
                'source_code' => $canonicalCode,
                'create_time' => nowTime(),
                'over_time'   => Date('Y-m-d H:i:s', strtotime(nowTime()) + 3600 * 24),
            ];
            $result = self::create($fileData);
        } else {
            $result = $inviteLink;
        }
        return $result;
    }

    /**
     * @throws \Exception
     */
    public static function resolveSourceCode(string $inviteType, $sourceRef): string
    {
        $sourceRef = trim((string) $sourceRef);
        if ($sourceRef === '') {
            throw new \Exception('该资源不存在', 1);
        }
        switch ($inviteType) {
            case 'project':
                $source = Project::resolveByRef($sourceRef);
                if (!$source) {
                    throw new \Exception('该资源不存在', 1);
                }
                return $source['code'];
            case 'organization':
                $source = Organization::where(['code' => $sourceRef])->find();
                if (!$source) {
                    throw new \Exception('该资源不存在', 1);
                }
                return $source['code'];
            default:
                throw new \Exception('邀请类型无效', 1);
        }
    }

    /**
     * @throws \Exception
     */
    public static function getInviteDetail($linkCode)
    {
        $link = self::where(['code' => $linkCode])->find();
        if (!$link) {
            throw new \Exception('该链接已失效', 1);
        }
        if (nowTime() >= $link['over_time']) {
            throw new \Exception('该链接已失效', 1);
        }
        $link = $link->toArray();
        $linkDetail = null;
        switch ($link['invite_type']) {
            case 'project':
                $link['name'] = '';
                $linkDetail = Project::where(['code' => $link['source_code']])->withoutField('id')->find();
                if ($linkDetail) {
                    $link['name'] = $linkDetail['name'];
                }
                break;
            case 'organization':
                $link['name'] = '';
                $linkDetail = Organization::where(['code' => $link['source_code']])->withoutField('id')->find();
                if ($linkDetail) {
                    $link['name'] = $linkDetail['name'];
                }
                break;
        }
        $link['member'] = Member::where(['code' => $link['create_by']])->withoutField('id')->find();
        $link['sourceDetail'] = $linkDetail;
        return $link;
    }
}
