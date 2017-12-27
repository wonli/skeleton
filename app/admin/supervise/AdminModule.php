<?php
/**
 * @author wonli <wonli@live.com>
 */

namespace app\admin\supervise;

use Cross\MVC\Module;

/**
 * cpa基类
 *
 * @author wonli <wonli@live.com>
 * Class AdminModule
 * @package modules\admin
 */
class AdminModule extends Module
{
    /**
     * 管理员表
     *
     * @var string
     */
    protected $t_admin = 'cpa_admin';

    /**
     * 角色表名
     *
     * @var string
     */
    protected $t_role = 'cpa_acl_role';

    /**
     * 权限表
     *
     * @var string
     */
    protected $t_acl_menu = 'cpa_acl_menu';

    /**
     * 存储密保卡的表名
     *
     * @var string
     */
    protected $t_security_card = 'cpa_security_card';
}
