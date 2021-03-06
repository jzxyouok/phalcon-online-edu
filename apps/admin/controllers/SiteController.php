<?php
/**
 * @CreateTime: 2016/5/14 13:24
 * @Author: iteny <8192332@qq.com>
 * @blog: http://itenyblog.com
 */
namespace Hemacms\Admin\Controllers;
use Phalcon\Mvc\Controller;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Regex;
use Phalcon\Validation\Validator\PresenceOf;
use Hemacms\Admin\Models\User;
class SiteController extends AdminBaseController
{
    //读取菜单
    public function menuAction(){
        $cacheKey = 'admin-menu.cache';
        $adminMenu   = $this->safeCache->get($cacheKey);
        if($adminMenu === null){
            $sql = "SELECT * FROM Hemacms\Admin\Models\AclResource ORDER BY sort ASC";
            $adminMenu = $this->modelsManager->executeQuery($sql);
            $adminMenu = $this->function->recursive($adminMenu->toArray());
            $this->safeCache->save($cacheKey,$adminMenu,$this->config->admincache->adminmenu);
        }
        $this->view->adminMenu = $adminMenu;
    }
    //添加&&修改菜单
    public function addEditMenuAction(){
        if($this->request->isPost() && $this->request->getPost('addEditMenu')){
            $this->view->disable();
            $validation = new Validation();
            $validation->add('name',new PresenceOf(array(
                            'message' => '菜单名称不能为空'
                        )))
                        ->add('name', new Regex(array(
                            'message' => '菜单名称必须是中文',
                            'pattern' => '/^[\x{4e00}-\x{9fa5}]+$/u'
                        )))
                        ->add('controller',new PresenceOf(array(
                            'message' => '控制器名称不能为空'
                        )))
                        ->add('controller', new Regex(array(
                           'message' => '控制器名称必须是英文字符串',
                           'pattern' => '/^[A-Za-z]+$/'
                        )))
                        ->add('action',new PresenceOf(array(
                            'message' => '方法名称不能为空'
                        )))
                        ->add('action', new Regex(array(
                            'message' => '方法名称必须是英文字符串',
                            'pattern' => '/^[A-Za-z]+$/'
                        )))
                        ->add('pid',new PresenceOf(array(
                            'message' => '父ID不能为空'
                        )))
                        ->add('pid', new Regex(array(
                            'message' => '父ID必须是整数',
                            'pattern' => '/^[0-9]\d*$/'
                        )))
                        ->add('sort',new PresenceOf(array(
                            'message' => '排序数不能为空'
                        )))
                        ->add('sort', new Regex(array(
                            'message' => '排序数必须是整数',
                            'pattern' => '/^[1-9]\d*$/'
                        )));
            $messages = $validation->validate($this->request->getPost());
            if (count($messages)) {
                $str = '';
                foreach ($messages as $message) {
                    $str .= $message.'!<br>';
                }
                exit(json_encode(array('info'=>$str)));
            }else{
                if($this->request->getPost('id')){
                    $menu['id'] = $this->request->getPost('id','int');
                }
                $menu['name'] = $this->request->getPost('name','string');
                $menu['controller'] = $this->request->getPost('controller','string');
                $menu['action'] = $this->request->getPost('action','string');
                $menu['isshow'] = $this->request->getPost('isshow','int');
                $menu['pid'] = $this->request->getPost('pid','int');
                $menu['sort'] = $this->request->getPost('sort','int');
                $menu['icon'] = $this->request->getPost('icon','string');
                if($this->request->getPost('id')){
                    $sql = "UPDATE Hemacms\Admin\Models\AclResource SET name=?0,controller=?1,action=?2,isshow=?3,pid=?4,sort=?5,icon=?6 WHERE id={$menu['id']}";
                    $status = $this->modelsManager->executeQuery($sql,array(
                        0 => $menu['name'],
                        1 => $menu['controller'],
                        2 => $menu['action'],
                        3 => $menu['isshow'],
                        4 => $menu['pid'],
                        5 => $menu['sort'],
                        6 => $menu['icon']
                    ));
                    $msg = $this->function->returnMsg("菜单修改成功","菜单修改失败",$status->success());
                }else{
                    $sql = "INSERT INTO Hemacms\Admin\Models\AclResource (name,controller,action,isshow,pid,sort,icon) VALUES (:name:,:controller:,:action:,:isshow:,:pid:,:sort:,:icon:)";
                    $status = $this->modelsManager->executeQuery($sql,$menu);
                    $msg = $this->function->returnMsg("添加菜单成功","添加菜单失败",$status->success());
                }
                if($status->success()){
                    $keys = $this->safeCache->queryKeys();
                    foreach ($keys as $key) {
                        $this->safeCache->delete($key);
                    }
                }
                exit(json_encode($msg));
            }
        }else{
            $cacheKey = 'admin-select.cache';
            $adminSelect   = $this->safeCache->get($cacheKey);

            if($adminSelect === null){
                $sql = "SELECT * FROM Hemacms\Admin\Models\AclResource ORDER BY sort ASC";
                $adminSelect = $this->modelsManager->executeQuery($sql);
                $adminSelect = $this->function->recursiveTwo($adminSelect->toArray());
                $this->safeCache->save($cacheKey,$adminSelect,$this->config->admincache->adminmenu);
            }
            $this->view->adminSelect = $adminSelect;
            if($this->request->getQuery('id')){
                $id = $this->request->getQuery('id','int');
                $sql = "SELECT * FROM Hemacms\Admin\Models\AclResource WHERE id = :id:";
                $thisMenu = $this->modelsManager->executeQuery($sql,array(
                    'id' => $id
                ))->getFirst();
                $this->view->thismenu = $thisMenu->toArray();
                $this->view->pick("Site/editMenu");
            }else{
                $pid = $this->request->getQuery('pid','int');
                $pid = $pid != '' ? $pid : 0;
                $this->view->pid = $pid;
                $this->view->pick("Site/addMenu");
            }
        }
    }
    //菜单排序
    public function sortMenuAction(){
        $this->view->disable();
        $sortMenu = $this->request->getPost('sort');
        $ids = implode(',', array_keys($sortMenu));
        $sql = "UPDATE Hemacms\Admin\Models\AclResource SET sort = CASE id ";
        foreach ($sortMenu as $id => $sort) {
            $sql .= sprintf("WHEN %d THEN %d ", $id, $sort);
        }
        $sql .= "END WHERE id IN ($ids)";
        $status = $this->modelsManager->executeQuery($sql);
        $msg = $this->function->returnMsg("菜单排序成功","菜单排序失败",$status->success());
        $this->safeCache->delete('admin-menu.cache');
        $this->safeCache->delete('admin-select.cache');
        exit(json_encode($msg));
    }
    // 删除菜单
    public function delMenuAction(){
        $this->view->disable();
        $id = $this->request->getPost('id','int');
        if($this->request->isPost() && $id){
            $sql = "SELECT id,pid FROM Hemacms\Admin\Models\AclResource";
            $menuid = $this->modelsManager->executeQuery($sql);
            $delid = $this->function->getAllChild($menuid->toArray(),$id);
            $delid[] = $id;
            $delid = implode(',',$delid);
            $sql = "DELETE FROM Hemacms\Admin\Models\AclResource WHERE id IN($delid) ";
            $status = $this->modelsManager->executeQuery($sql);
            $msg = $this->function->returnMsg("菜单删除成功","菜单删除失败",$status->success());
            if($status->success()){
                $keys = $this->safeCache->queryKeys();
                foreach ($keys as $key) {
                    $this->safeCache->delete($key);
                }
            }
            exit(json_encode($msg));
        }
    }
    //用户组管理
    public function groupAction(){
        $sql = "SELECT * FROM Hemacms\Admin\Models\AclGroup ORDER BY sort ASC";
        $group = $this->modelsManager->executeQuery($sql);
        $this->view->group = $group->toArray();
    }
    //用户组权限设置
    public function setGroupAction(){
        if($this->request->getQuery('id') == '1'){
            echo "不允许对超级管理员授权";
            exit;
        }
        if($this->request->isPost() && $this->request->getPost('setGroup')){
            $this->view->disable();
            $resIds = $this->request->getPost('resid');
            $id = $this->request->getPost('id','int');
            $sql = "UPDATE Hemacms\Admin\Models\AclGroup SET resource = ?0 WHERE id = $id";
            $status = $this->modelsManager->executeQuery($sql,array(
                0 => $resIds
            ));
            $msg = $this->function->returnMsg("授权成功","授权失败",$status->success());
            $this->safeCache->delete('acl');
            exit(json_encode($msg));
        }else{
            $id = $this->request->getQuery('id','int');
            $sql = "SELECT id,name,pid FROM Hemacms\Admin\Models\AclResource";
            $resource = $this->modelsManager->executeQuery($sql);
            $resource = $resource->toArray();
            $groupSql = "SELECT id,title,resource FROM Hemacms\Admin\Models\AclGroup WHERE id = :id:";
            $group = $this->modelsManager->executeQuery($groupSql,array(
                'id' => $id
            ))->getFirst();
            $resourceIds = explode(",",$group->resource);
            $data = $this->function->treeRule($resource);
            $data = $this->function->treeState($data,$resourceIds);
            $this->view->resource = json_encode($data);
            $this->view->id = $id;
            $this->view->rolename = $group->title;
        }
    }
    //添加或修改用户组
    public function addEditGroupAction(){
        if($this->request->getQuery('id') == '1'){
            echo "不允许对超级管理员修改";
            exit;
        }
        if($this->request->isPost() && $this->request->getPost('addEditGroup')){
            $this->view->disable();
            $validation = new Validation();
            $validation->add('title',new PresenceOf(array(
                'message' => '用户组名称不能为空'
                )))
                ->add('title', new Regex(array(
                    'message' => '用户组名称必须是中文',
                    'pattern' => '/^[\x{4e00}-\x{9fa5}]+$/u'
                )))
                ->add('role',new PresenceOf(array(
                    'message' => '用户组英文名称不能为空'
                )))
                ->add('role', new Regex(array(
                    'message' => '用户组英文名称必须是英文字符串',
                    'pattern' => '/^[A-Za-z]+$/'
                )))
                ->add('sort',new PresenceOf(array(
                    'message' => '排序数不能为空'
                )))
                ->add('sort', new Regex(array(
                    'message' => '排序数必须是整数',
                    'pattern' => '/^[1-9]\d*$/'
                )));
            $messages = $validation->validate($this->request->getPost());
            if (count($messages)) {
                $str = '';
                foreach ($messages as $message) {
                    $str .= $message.'!<br>';
                }
                exit(json_encode(array('info'=>$str)));
            }else{
                if($this->request->getPost('id')){
                    $group['id'] = $this->request->getPost('id','int');
                }
                $group['title'] = $this->request->getPost('title','string');
                $group['role'] = $this->request->getPost('role','string');
                $group['status'] = $this->request->getPost('status','int');
                $group['sort'] = $this->request->getPost('sort','int');
                if($this->request->getPost('id')){
                    $sql = "UPDATE Hemacms\Admin\Models\AclGroup SET title=?0,role=?1,status=?2,sort=?3 WHERE id={$group['id']}";
                    $status = $this->modelsManager->executeQuery($sql,array(
                        0 => $group['title'],
                        1 => $group['role'],
                        2 => $group['status'],
                        3 => $group['sort']
                    ));
                    $msg = $this->function->returnMsg("修改用户组成功","修改用户组失败",$status->success());
                }else{
                    $sql = "INSERT INTO Hemacms\Admin\Models\AclGroup (title,role,status,sort) VALUES (:title:,:role:,:status:,:sort:)";
                    $status = $this->modelsManager->executeQuery($sql,$group);
                    $msg = $this->function->returnMsg("添加用户组成功","添加用户组失败",$status->success());
                }
                if($status->success()){
                    $this->safeCache->delete('acl');
                }
                exit(json_encode($msg));
            }
        }else{
            if($this->request->getQuery('id')){
                $id = $this->request->getQuery('id','int');
                $sql = "SELECT * FROM Hemacms\Admin\Models\AclGroup WHERE id = :id:";
                $thisGroup = $this->modelsManager->executeQuery($sql,array(
                    'id' => $id
                ))->getFirst();
                $this->view->thisgroup = $thisGroup->toArray();
                $this->view->pick("Site/editGroup");
            }else{
                $this->view->pick("Site/addGroup");
            }
        }
    }
    //删除用户组
    public function delGroupAction(){
        if($this->request->getQuery('id') == '1'){
            echo "不允许删除超级管理员";
            exit;
        }
        $this->view->disable();
        $id = $this->request->getPost('id','int');
        if($this->request->isPost() && $id){
            $sql = "DELETE FROM Hemacms\Admin\Models\AclGroup WHERE id = :id:";
            $status = $this->modelsManager->executeQuery($sql,array(
                'id' => $id
            ));
            $msg = $this->function->returnMsg("用户组删除成功","用户组删除失败",$status->success());
            if($status->success()){
                $this->safeCache->delete('acl');
            }
            exit(json_encode($msg));
        }
    }
    //用户管理
    public function userAction(){
        $user = $this->db->fetchAll("SELECT u.id,u.username,u.create_time,u.create_ip,u.email,u.remark,u.status,ug.uid,g.role,g.title FROM hm_user u JOIN hm_acl_user_group ug JOIN hm_acl_group g WHERE ug.uid = u.id AND ug.group_id = g.id");
        $this->view->user = $user;
    }
    //添加或修改用户
    public function addEditUserAction(){
        if($this->request->getQuery('id') == '1'){
            echo "不允许对超级管理员修改";
            exit;
        }
        if($this->request->isPost() && $this->request->getPost('addEditUser')){
            $this->view->disable();
            $validation = new Validation();
            $validation->add('title',new PresenceOf(array(
                'message' => '用户组名称不能为空'
            )))
                ->add('title', new Regex(array(
                    'message' => '用户组名称必须是中文',
                    'pattern' => '/^[\x{4e00}-\x{9fa5}]+$/u'
                )))
                ->add('role',new PresenceOf(array(
                    'message' => '用户组英文名称不能为空'
                )))
                ->add('role', new Regex(array(
                    'message' => '用户组英文名称必须是英文字符串',
                    'pattern' => '/^[A-Za-z]+$/'
                )))
                ->add('sort',new PresenceOf(array(
                    'message' => '排序数不能为空'
                )))
                ->add('sort', new Regex(array(
                    'message' => '排序数必须是整数',
                    'pattern' => '/^[1-9]\d*$/'
                )));
            $messages = $validation->validate($this->request->getPost());
            if (count($messages)) {
                $str = '';
                foreach ($messages as $message) {
                    $str .= $message.'!<br>';
                }
                exit(json_encode(array('info'=>$str)));
            }else{
                if($this->request->getPost('id')){
                    $group['id'] = $this->request->getPost('id','int');
                }
                $group['title'] = $this->request->getPost('title','string');
                $group['role'] = $this->request->getPost('role','string');
                $group['status'] = $this->request->getPost('status','int');
                $group['sort'] = $this->request->getPost('sort','int');
                if($this->request->getPost('id')){
                    $sql = "UPDATE Hemacms\Admin\Models\AclGroup SET title=?0,role=?1,status=?2,sort=?3 WHERE id={$group['id']}";
                    $status = $this->modelsManager->executeQuery($sql,array(
                        0 => $group['title'],
                        1 => $group['role'],
                        2 => $group['status'],
                        3 => $group['sort']
                    ));
                    $msg = $this->function->returnMsg("修改用户组成功","修改用户组失败",$status->success());
                }else{
                    $sql = "INSERT INTO Hemacms\Admin\Models\AclGroup (title,role,status,sort) VALUES (:title:,:role:,:status:,:sort:)";
                    $status = $this->modelsManager->executeQuery($sql,$group);
                    $msg = $this->function->returnMsg("添加用户组成功","添加用户组失败",$status->success());
                }
                if($status->success()){
                    $this->safeCache->delete('acl');
                }
                exit(json_encode($msg));
            }
        }else{
            $cacheKey = 'admin-group.cache';
            $adminGroup   = $this->safeCache->get($cacheKey);
            if($adminGroup === null){
                $sql = "SELECT * FROM Hemacms\Admin\Models\AclGroup ORDER BY sort ASC";
                $adminGroup = $this->modelsManager->executeQuery($sql);
                $this->safeCache->save($cacheKey,$adminGroup,$this->config->admincache->adminmenu);
            }
            $this->view->adminGroup = $adminGroup;
            if($this->request->getQuery('id')){
                $id = $this->request->getQuery('id','int');
                $sql = "SELECT * FROM Hemacms\Admin\Models\AclGroup WHERE id = :id:";
                $thisGroup = $this->modelsManager->executeQuery($sql,array(
                    'id' => $id
                ))->getFirst();
                $this->view->thisgroup = $thisGroup->toArray();
                $this->view->pick("Site/editUser");
            }else{
                $this->view->pick("Site/addUser");
            }
        }
    }
    public function backupAction(){
        $this->view->disable();
//        $user = User::findFirst();
////        var_dump($user->toArray());die;
//        $group = $user->getAclUserGroup();
//        foreach($group as $rp){
//            echo '<br>rp:</br>';
//            var_dump($rp->toArray());
//        }
        $id = array(
            'id' => 1
        );

//        $id = 1;
        $t = $this->db->fetchOne("SELECT u.id,ug.uid,g.role,g.title FROM hm_user u JOIN hm_acl_user_group ug JOIN hm_acl_group g WHERE ug.uid = u.id AND ug.group_id = g.id AND u.id = {$id['id']} LIMIT 1");
//        $t = array_coarrlumn($t);
//        var_dump($t);
//        echo $t['role'];
        $ss = $this->session->get('userInfo');
        var_dump($ss);
//        echo $t['role'];
//        $sql = 'SELECT u.*,ug.* FROM Hemacms\Admin\Models\User u JOIN Hemacms\Admin\Models\AclUserGroup ug ' .
//            'WHERE ug.uid = u.id';
//        $tags = $this->modelsManager->executeQuery($sql)->getFirst();
//        echo $tags->u->username;
    }
}