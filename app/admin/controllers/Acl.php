<?php
/**
 * @Author: wonli <wonli@live.com>
 * Acl.php
 */

namespace app\admin\controllers;

use modules\admin\AclModule;
use modules\admin\SecurityModule;

/**
 * 权限管理(菜单,角色及用户)
 * @Auth wonli <wonli@live.com>
 *
 * Class Acl
 * @package app\admin\controllers
 */
class Acl extends Admin
{
    /**
     * 权限控制module
     *
     * @var AclModule
     */
    protected $ACL;

    function __construct()
    {
        parent::__construct();
        $this->ACL = new AclModule;
    }

    function index()
    {
        $this->to("acl:navManager");
    }

    /**
     * 子菜单管理
     *
     * @cp_params id
     */
    function editMenu()
    {
        $id = (int)$this->params['id'];
        if ($this->is_post()) {
            if (!empty($_POST['menu'])) {
                $this->ACL->saveMenu($_POST['menu']);
            }

            if (!empty($_POST['customMenu'])) {
                $this->ACL->saveMenu($_POST['customMenu']);
            }

            $this->return_referer();
        } else {
            $menu_list = $this->ACL->getMenuAllDate($id);
            if (false === $menu_list) {
                $this->to('acl');
            }

            $this->data['menu_list'] = $menu_list;
        }

        $this->display($this->data);
    }

    /**
     * 导航菜单管理
     */
    function navManager()
    {
        if ($this->is_post()) {
            if (!empty($_POST['addNav'])) {
                $this->ACL->saveNav($_POST['addNav']);
            }

            if (!empty($_POST['nav'])) {
                $this->ACL->saveNav($_POST['nav']);
            }

            $this->to('acl:navManager');
        }

        $un_save_menu = array();
        $this->ACL->initMenuList();

        $this->data['menu'] = $this->ACL->getNavList($un_save_menu);
        $this->data['un_save_menu'] = $un_save_menu;

        $this->display($this->data);
    }

    /**
     * 删除
     *
     * @cp_params id, e
     */
    function del()
    {
        if (!empty($this->params['id'])) {
            $this->ACL->delNav((int)$this->params['id']);
        }

        if (!empty($this->params['e'])) {
            $this->to('acl:editMenu', array('id' => (int)$this->params['e']));
        }

        $this->to('acl:navManager');
    }

    /**
     * 添加管理角色
     */
    function addRole()
    {
        $menu_list = $this->ACL->initMenuList();

        if ($this->is_post()) {
            if (!empty($_POST['name']) && !empty($_POST['menu_id'])) {
                $menu_set = $_POST ['menu_id'];
                $ret = $this->ACL->saveRoleMenu($_POST['name'], $menu_set);

                if ($ret['status'] == 1) {
                    $this->to('acl:roleList');
                } else {
                    $data ['status'] = $ret['status'];
                }
            } else {
                $this->data ['status'] = 100670;
            }
        }

        $this->data ['menu_list'] = $menu_list;
        $this->display($this->data);
    }

    /**
     * 角色列表
     */
    function roleList()
    {
        $this->data ['role_list'] = $this->ACL->getRoleList();
        if ($this->is_post()) {
            $ret = $this->ACL->editRoleMenu($_POST['rid'], $_POST['name'], $_POST['menu_id']);
            if ($ret['status'] == 1) {
                $this->to("acl:roleList");
            }
        }

        $this->display($this->data);
    }

    /**
     * 编辑角色
     *
     * @cp_params rid
     * @return array|string
     */
    function editRole()
    {
        if (empty($this->params['rid'])) {
            $this->to('acl');
        }

        $rid = (int)$this->params['rid'];
        $role_info = $this->ACL->getRoleInfo(array('id' => $rid));
        if (empty($role_info)) {
            $this->to('acl');
        }

        if ($this->is_post()) {
            $this->ACL->editRoleMenu($rid, $_POST['name'], $_POST['menu_id']);
            $this->to('acl:editRole', array('rid' => $this->params['rid']));
        }

        $menu_list = $this->ACL->initMenuList();
        $this->data['role_info'] = $role_info;
        $this->data['menu_list'] = $menu_list;

        $this->display($this->data);
    }

    /**
     * 删除角色
     *
     * @cp_params rid
     */
    function delRole()
    {
        $is_ajax = $this->is_ajax_request();
        $rid = $is_ajax ? (int)$_GET['rid'] : (int)$this->params['rid'];

        $ret = $this->ACL->delRole($rid);
        if ($is_ajax) {
            echo (int)$ret;
        } else {
            $this->to('acl:roleList');
        }
    }

    /**
     * 管理员列表
     */
    function user()
    {
        $this->data ['u'] = $this->ADMIN->getAdminUserList();
        $this->data ['roles'] = $this->ACL->getRoleList();

        if ($this->is_post()) {
            $error = 0;
            $a = &$_POST['a'];
            foreach ($a as $k => $v) {
                if (isset($v['t']) && ($v['t'] == 'on' || $v['t'] == 1)) {
                    $v['t'] = 1;
                } else {
                    $v['t'] = 0;
                }

                if (isset($v['usc']) && ($v['usc'] == 'on' || $v['usc'] == 1)) {
                    $v['usc'] = 1;
                } else {
                    $v['usc'] = 0;
                }

                if ($k == '+') {
                    if (!empty($v ['name']) && !empty($v ['password'])) {
                        $ret = $this->ADMIN->addAdmin($v);
                    } else {
                        $ret['status'] = 1;
                    }
                } else {
                    if (!empty($v['name'])) {
                        $ret = $this->ADMIN->update($k, $v);
                    } else {
                        $ret = $this->ADMIN->del(array('id' => $k));
                    }
                }

                if ($ret['status'] != 1) {
                    $error++;
                    $this->data['status'] = $ret['status'];
                    break;
                }
            }

            if ($error == 0) {
                $this->to('acl:user');
            }
        }

        $this->display($this->data);
    }

    /**
     * 操作密保卡
     */
    function userSecurityCard()
    {
        $op = &$this->params['op'];
        $user = &$this->params['user'];

        $SEC = new SecurityModule();
        if ($op == 'bind') {
            $bind = $SEC->checkBind($user);
            if (!$bind) {
                $SEC->bindCard($user);
            }
        } else {
            $SEC->unBind($user, false);
        }

        $this->to('acl:user');
    }

    /**
     * 删除管理员
     *
     * @cp_params uid
     */
    function delUser()
    {
        $uid = (int)$this->params['uid'];
        $this->ADMIN->del(array('id' => $uid));
        $this->to('acl:user');
    }
}