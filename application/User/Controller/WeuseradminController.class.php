<?php
/**
 * 参    数：
 * 作    者：lht
 * 功    能：OAth2.0协议下第三方登录数据报表
 * 修改日期：2013-12-13
 */
namespace User\Controller;
use Common\Controller\AdminbaseController;
class WeuseradminController extends AdminbaseController {
	
	//用户列表
	function index(){
		$oauth_user_model = D("Common/WeUsers");
		$count=$oauth_user_model->where(array("status"=>1))->count();
		$page = $this->page($count, 20);
		$lists = $oauth_user_model
		->where(array("status"=>1))
		->order("create_time DESC")
		->limit($page->firstRow . ',' . $page->listRows)
		->select();
		$this->assign("page", $page->show('Admin'));
		$this->assign('lists', $lists);
		$this->display();
	}
	
	//删除用户
	function delete(){
		$id=intval($_GET['id']);
		if(empty($id)){
			$this->error('非法数据！');
		}
		$rst = D("Common/WeUsers")->where(array("id"=>$id))->delete();
		if ($rst!==false) {
			$this->success("删除成功！", U("weuseradmin/index"));
		} else {
			$this->error('删除失败！');
		}
	}
	
	
}