<?php
class content_controller extends comm_controller{
	public $fid_str='';

	protected function _initialize() {
		parent::_initialize();
	}

	/**
	 * [从上往下递归查找子分类]
	 */
	public function _fid2next($fid){
		if($fid){
			$a1 = $this->D2('category','fid='.$fid);
			foreach($a1 as $k=>$v){
				$this->fid_str .= ','.$v['id'];
				$this->_fid2next($v['id']);
			}
		}
	}
	public function fid2next($fid=0){
		if($fid>0){
			$this->fid_str .= $fid;
			$this->_fid2next($fid);
		}
		return $this->fid_str;
	}



	/**
	 * [从上往下递归查找子分类]
	 * @return [type] [description]
	 */
	public function _nextCategory($fid,$pid,$level){
		if($fid && $pid){
			$a1 = $this->D2('category','fid='.$fid.' and pid='.$pid);
			foreach($a1 as $k=>$v){
				$a1[$k]['level'] = $level;
				$a1[$k]['child']=$this->_nextCategory($v['id'],$pid,$level+1);
			}
			return $a1;
		}
	}

	/**
	 * 根据ID递归查询子分类
	 * @return [type] [description]
	 */
	public function getCategoryTree($pid){
		if($pid){
			$a = $this->D2('category','fid=0 and pid='.$pid);
			foreach($a as $k=>$v){
				$a[$k]['child']=$this->_nextCategory($v['id'],$pid,1);
			}
			return $a;
		}
	}

	/**
	 * 格式化子分类
	 * @param  [type] $arr [description]
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function formatTree($arr,$str='|—'){
		$this->tree_format = array();
		$this->_formatTree($arr);
		foreach($this->tree_format as $k=>$v){
			unset($this->tree_format[$k]['child']);
			$this->tree_format[$k]['html'] = str_repeat($str,$v['level']);
		}
		return $this->tree_format;
	}

	/**
	 * 格式化子分类
	 */
	public function _formatTree($arr){
		if($arr){
			foreach($arr as $k=>$v){
				if($v['child']){
						$next_arr = $v['child'];
					$v['child']='';
					$this->tree_format[]=$v;
					$this->_formatTree($next_arr);
				}
				else{
					$this->tree_format[]=$v;
				}
			}
		}
	}

	/**
	 * 内容模块检查PID参数
	 * @return [Int] [PID的值或错误信息]
	 */
	public function checkPid(){
		$pid = F::input_int('get.pid');
		$fid = F::input_int('get.fid');
		if($pid>0){
			$pid_item = $this->D1('section',$pid);
			$this->view['pid_item']=$pid_item;
			$this->view['pid']=$pid;
			$this->fid = $this->view['fid']=$fid;
			if($fid>0){
				$this->level_ids=[$fid];
				$this->_getPid($fid);
				$leveldata = [];
				foreach(array_reverse($this->level_ids) as $k=>$v){
					if($v) $leveldata[]=$this->D1('category',$v);
				}
				$this->view['leveldata']=$leveldata;
			}
			return $pid;
		}
		else{
			F::redirect('栏目错误，请重新进入。');
		}
	}

	/**
	 * 递归查找上级ID直至0
	 * @return [type] [description]
	 */
	public function _getPid($last_id){
		$f_id = $this->val('category','fid','id='.$last_id);
		if($f_id>0){
			$this->level_ids[]=$f_id;
			$this->_getPid($f_id);
		}
	}

	//模块首页
	public function index(){
		$pid = $this->checkPid();
		$tree = $this->getCategoryTree($pid);
		$tree = $this->formatTree($tree);
		$fid = F::input_int('get.fid');
		$keyword = F::input('get.keyword');
		$o1 = F::input('get.o1');//排序名称
		$o2 = F::input('get.o2');//升序降序

		$list_section = $this->D1('section',$pid);

		//读取需要显示的标题
		$showTitle = $this->D3('field','is_show=1 and pid='.$pid,'sort');
		// 读取所有的标题[修复]
		$Title=$this->db->table('field')->where('pid='.$pid)->order('sort')->getlist();

		$fieldStr = '';
		$fieldTitle = array();
		$fielden = array();
		$key_search = '';
		foreach($showTitle as $k=>$v){
			$fieldStr .= ','.$v['field_name'];
			$fieldTitle[] = $v['name'];
			$fielden[] = $v['field_name'];
			if($keyword){
				$key_search .= " or ".$v['field_name']." like '%".$keyword."%'";
			}
		}
		// [修复]
		foreach($Title as $k=>$v){
			$fielden_title[] = $v['field_name'];
		}


		// 搜索条件
		$where = '1=1';
		$fids = $this->fid2next($fid);
		if($fids){
			$where .= ' and fid in('.$fids.')';
		}
		if($keyword){
			$this->view['keyword']=$keyword;
			$key_search = substr($key_search,0-(strlen($key_search)-4));
			$where .= ' and ('.$key_search.')';
		}

		//排序
		$order='sort desc,id asc';
		if($o1 && $o2){
			$order = $o1.' '.$o2;
		}

    	if($list_section['pagesize']){//有设置分页
			$page = $this->getpage(array(
				'table'=>$list_section['ename'],
				'where'=>$where,
				'order'=>$order,
				'limit'=>$list_section['pagesize'],
			));
			$list = $page['list'];
			$this->view['pageurl'] = $page['url'];
    	}
    	else{
    		$list = $this->db->table($list_section['ename'])->where($where)->order($order)->getlist();
			$this->view['pageurl'] = '';
		}
		foreach($list as $k=>$v){
			$list[$k]['category']=$this->fid2arr($v['fid'],$v['pid']);
		}
		$this->view['tree'] = $tree;
		$this->view['pagelist'] = $list;//内容列表
		$this->view['pagelist_size'] = sizeof($list);
		$this->view['list_section'] = $list_section;
		$this->view['fieldTitle'] = $fieldTitle;//中文字段名称
		$this->view['fielden'] = $fielden;//英文字段名称
		F::session("Englishtitle",$fielden_title);//[修复]
		$this->view('content_index.html');
	}

	/**
	 * 复制数据/删除数据
	 * @return [type] [页面跳转]
	 * [更新:删除多条数据]
	 */
	public function movedata(){
		$pid = F::input_int('get.pid');
		$flag = F::input('get.flag');
		$ids = F::input('get.ids');
		$newfid = F::input_int('get.newfid');
		$section = $this->D1('section',$pid);
		$table = $this->db->pre($section['ename']);
		if($flag=='move'){
			$sql = 'update '.$table.' set fid='.$newfid.' where id in('.$ids.')';
			$str='移动成功';
		}
		else if($flag=='delete'){
			$sql = 'delete from '.$table.' where id in('.$ids.')';
			$str = '删除成功';
		}
		else{
			$fields = 'pid,fid,is_pass,is_best,add_time';
			$field = $this->D2('field','pid='.$pid);
			foreach($field as $k=>$v){
				$fields .= ','.$v['field_name'];
			}
			$sql = 'insert into '.$table.'('.$fields.') select '.str_replace(',fid,',','.$newfid.',',$fields).' from '.$table.' where id in ('.$ids.')';
			$str='复制成功';
		}
		$result=$this->exec($sql);
		if($result){
			F::alert($str,$this->url('content/index',['pid'=>$pid,'fid'=>$newfid]));
		}
		$url = $this->url('content/index',['pid'=>$pid,'fid'=>$newfid]);
		F::go($url);
	}

	/**
	 * 将FID反查询到根分类
	 * @param  [type] $fid [末端的FID]
	 * @return [type]      [array]
	 */
	public function fid2arr($fid=0,$pid=0){
		if(!$pid){return [];}
		$arr = array();
		if($fid>0){
			for($i=0;$i<100;$i++){
				if($fid){
					$tmp_arr = $this->db->table('category')->where('id='.$fid)->get();
					$name = $tmp_arr['name'];
					$arr[] = ['fid'=>$fid,'name'=>$name];
					$fid = $tmp_arr['fid'];
				}
				else{
					break;
				}
			}
		}
		else{
			$arr[] = ['fid'=>0];
		}
		return array_reverse($arr);
	}

























//-----------------------------------------添加内容相关的控制器--------------------------------------------


	//添加、修改内容
	public function add(){
		$pid = $this->checkPid();
		$tree = $this->getCategoryTree($pid);
		$tree = $this->formatTree($tree);
		$id = F::input_int('get.id');

		//构建表单
		$field_arr = $this->db->table('field')->where('pid='.$pid)->order('sort')->getlist();
		if(!$field_arr){
			F::alert('请先配置模块的表单元素。');
		}
		if($id>0){
			//查找表名
			$db_arr = $this->db->table('section')->where('id='.$pid)->get();
			$item = $this->D1($db_arr['ename'],$id);
			$item_key = array_keys($item);
			//给表单赋值
			foreach($field_arr as $k=>$v){
				foreach($item_key as $k1=>$v1){
					if($v['field_name']==$v1){
						$field_arr[$k]['value']=$item[$v1];
					}
				}
			}
			foreach($field_arr as $k=>$v){
				$field_arr[$k]['html']=$this->type2html($v);
			}
		}
		else{
			foreach($field_arr as $k=>$v){
				$field_arr[$k]['html']=$this->type2html($v);
			}
		}

		$this->imgresource();
		$this->view['tree'] = $tree;
		$this->view['field_arr'] = $field_arr;
		$this->view['list_section'] = $list_section;
		$this->view['id']=$id;
		$this->view['item']=$item;
		$this->view('content_add.html');
	}

	//浏览服务器图片[更新浏览服务器图片资源方法]
	public function imgresource(){
		$img = array('gif','png','jpg');//所有图片的后缀名
		$dir = 'public/uploads/*/';//文件夹名称
		$pic = array();
	foreach($img as $k=>$v){
    	$pattern = $dir.'*.'.$v;
    	$all = glob($pattern);
    	$pic = array_merge($pic,$all);
	}
		$this->view['pic'] = $pic;
	}

	/**
	 *  保存内容
	 */
	public function save(){
		if(IS_POST) {
			$data = F::input('post.');
			unset($data['file']);
			$id = $data['id'];
			$pid = $data['pid'];
			$data['add_time']=time();

			// 检查是否有必填项未填的
			$field_arr = $this->db->table('field')->where('is_must=1 and pid='.$pid)->order('sort')->getlist();
			foreach($field_arr as $k=>$v){
				if(!$data[$v['field_name']]){
					F::redirect('请输入'.$v['name'],'',1);
				}
			}

			//查找表名
			$db_arr = $this->db->table('section')->where('id='.$pid)->get();


			if($id>0){//修改

				$this->db->table($db_arr['ename'])->where('id='.$id)->update($data);
				F::redirect('修改成功',$this->url('content/index',['pid'=>$pid,'id'=>$id]),1);
			}
			else{// 添加数据
				if($db_arr['is_add']){
					//字段名和字段的值
					$coumlenamevalue=array();
					$coumlename=F::session("Englishtitle");
					$coumlevalue=array();
					foreach($coumlename as $k=>$v){
							$coumlevalue[$k]=$data[$v];
					}
					$coumlenamevalue = array_combine($coumlename, $coumlevalue);
								$max = $this->db->table($db_arr['ename'])->where('pid=?',array($pid))->max('sort');

								$max+=1;
								$old=array(
									'add_time'=>$data['add_time'],
									'pid'=>$pid,
									'fid'=>$data['fid'],
									// 'fid'=>$this->fid,
									'sort'=>$max,
								);
								$all=array();
								$all = $coumlenamevalue+$old;
								$this->ins($db_arr['ename'],$all);
						F::redirect('保存成功',$this->url('content/index',['pid'=>$pid]),1);
				}
				else{
					F::redirect('此分类不可添加内容',$this->url('content/index',['pid'=>$pid]),1);
				}
			}
		}
	}

	/**
	*移动内容
	*
	**/
	public function content_move(){
		$pid = $this->checkPid();
		$action = F::input('get.action');
		$id = F::input_int('get.id');
		$db_arr = $this->db->table('section')->where('id='.$pid)->get();
		if($id>0){
			$max = $this->db->table($db_arr['ename'])->where('pid=?',array($pid))->max('sort');
			if($max>1){
				$thisSort = $this->val($db_arr['ename'],'sort','id='.$id);
				if($action=='up' && $thisSort==1){
					$str = '已经移到最后位';
				}
				elseif($action=='up' && $thisSort>1){//可以向上移动
					$preid = $this->val($db_arr['ename'],'id','pid='.$pid.' and sort='.($thisSort-1));
					$this->db->table($db_arr['ename'])->where('id=?',array($id))->update([
						'sort'=>($thisSort-1)
					]);
					$this->db->table($db_arr['ename'])->where('id=?',array($preid))->update([
						'sort'=>$thisSort
					]);
					$str = '操作成功';
				}
				elseif($action=='down' && $thisSort<$max){//可以向下移动
					$preid = $this->val($db_arr['ename'],'id','pid='.$pid.' and sort='.($thisSort+1));
					$this->db->table($db_arr['ename'])->where('id=?',array($id))->update([
						'sort'=>($thisSort+1)
					]);
					$this->db->table($db_arr['ename'])->where('id=?',array($preid))->update([
						'sort'=>$thisSort
					]);
					$str = '操作成功';
				}
				elseif($action=='down' && $thisSort==$max){//可以向下移动
					$str = '已经移到第一位';
				}
			}
			else{
				$str = '无需移动';
			}
		}
		F::redirect($str,$this->url('content/index',['pid'=>$pid]),1);
	}

	/**
	 * 删除内容
	 */
	public function delete(){
		$id = F::input_int('get.id');
		$pid = F::input_int('get.pid');
		if($id>0){
			//查找表名
			$db_arr = $this->db->table('section')->where('id='.$pid)->get();
			if($db_arr['is_delete']){
				if($this->del($db_arr['ename'],$id)){
					$str = '删除成功';
				}
			}
			else{
				$str = '此内容不可以删除';
			}
		}
		else{
			$str = '参数错误';
		}
		F::redirect($str,$this->url('content/index',['pid'=>$pid]),1);
	}

	/**
	 * 删除图片[新增:多图上传删除方法]
	 */
	public function img_del(){
		$id=F::input('get.id');//标记数据
		$fname=F::input('get.fname');//动态获取列名
		$img_id=F::input('get.image_id');//获取删除的图片路径
		$pid=F::input('get.pid');//动态获取表名
		$all=$this->db->table('section')->where("id=".$pid)->get();
		$table=$all['ename'];//得到表名
		$all1=$this->db->table($table)->where("id=".$id)->get();
		$shuzu=$all1[$fname];
		$shuzu=str_replace(",".$img_id,"",$shuzu);
		$shuzu=str_replace($img_id.",","",$shuzu);
		$shuzu=str_replace($img_id,"",$shuzu);
		$sql=" update f_".$table." set ".$fname." = '".$shuzu."' where id = ".$id;
		if($this->db->query($sql)){
			F::redirect('删除成功','',1);
		}else{
			F::redirect('删除失败','',1);
		}
	}


	/**
	 * 将表单类型转为HTML代码
	 * @param  [type] $field_type [表单类型]
	 * @param  [type] $arr [参数数组]
	 * 'type'=表单类型
	 * 'name'=文本
	 * 'field_name'=字段名称
	 * 'field_long'=字段长度
	 * 'default'=设置的默认值
	 * 'is_must'=必填项
	 * 'value'=数据库已保存的值
	 * ''=
	 * @return [type]             [HTML代码]
	 */
	public function type2html($arr){
		$html = '';
		$field_type = $arr['type'];
		$cname = $arr['name'];//字段中文名称
		$fname = $arr['field_name'];//字段英文名称
		$idname = 'name="'.$fname.'" id="'.$fname.'"';//组织HTML的ID和Name
		$val = $arr['value']?$arr['value']:$arr['default'];//数据库保存的值
		$val = F::in($val);

		//拆分默认值
		if($arr['default']){
			$def_arr =	str_replace(PHP_EOL,"\n",$arr['default']);
			$def_arr = 	explode("\n", $def_arr);
		}

		//文本输入框
		if($field_type=='input'){

			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
							<input type="text" '.$idname.' value="'.$val.'" '.($arr['is_must']?'required lay-verify="required"':'').' placeholder="'.($arr['is_must']?'':'（选填）').'请输入'.$cname.'" class="layui-input">
						</div>
					</div>';
		}

		//隐藏域
		elseif($field_type=='hidden'){
			$html = '<input type="hidden" '.$idname.' value="'.$val.'">';
		}

		//开关
		elseif($field_type=='switch'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
							<input type="checkbox" '.$idname.' lay-skin="switch" value="1"'.($val?' checked':'').'>
						</div>
					</div>';
		}

		//单行数字
		elseif($field_type=='inputnumber'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
								<input type ="number" '.$idname.' '.($arr['is_must']?'required lay-verify="number"':'').'
							 autocomplete="off" class="layui-input layui-input-inline" placeholder="请输入'.$cname.'" value="'.$val.'" style="width:120px;">
						</div>
					</div>';
		}

		//多行文本输入
		elseif($field_type=='textarea'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
							<textarea '.$idname.' placeholder="请输入'.$cname.'内容" class="layui-textarea">'.$val.'</textarea>
						</div>
					</div>';
		}

		//多选框[更新：提交与显示]
		elseif($field_type=='checkbox'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
						<input type="hidden" name="'.$fname.'" />
						';
			if($def_arr){
				foreach($def_arr as $k9=>$v9){
					$html .= '<input type="checkbox"  class="js-checkbox-'.$fname.'"  value="'.$v9.'" title="'.$v9.'"' ;
					if(in_array($v9,explode("-",$arr['value'])))$html .= ' checked';
					$html .= '>';
				}
			}

			$html .= '</div></div>
				<script type="text/javascript">
				$(function(){
				var $checkbox_value="";
					$("#save-btn").on("click",function(){
						$("input[class=js-checkbox-'.$fname.']:checked").each(function(){
						$checkbox_value += $(this).val()+"-";
					});
						$checkbox_value = $checkbox_value.substring(0,$checkbox_value.length-1);
						$("input[name='.$fname.']").val($checkbox_value);
					});
				});
			</script>';
		}

		//单选框
		elseif($field_type=='radio'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">';
			if($def_arr){
				foreach($def_arr as $k9=>$v9){
					$html .= '<input type="radio" name="'.$fname.'" value="'.$v9.'" title="'.$v9.'"';
					if($arr['value']==$v9) $html .= ' checked';
					$html .= '>';
				}
			}
			$html .= '</div></div>';
		}

		//下拉列表
		elseif($field_type=='select'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
							<select '.$idname.' class="layui-input-inline"><option value=""></option>';
			if($def_arr){
				foreach($def_arr as $k9=>$v9){
					$html .= '<option value="'.$v9.'" title="'.$v9.'"';
					if($arr['value']==$v9) $html .= ' selected';
					$html .= '>'.$v9.'</option>';
				}
			}
			$html .= '</select></div></div>';
		}

		//文件上传
		elseif($field_type=='upload'){
			$html = '<div class="layui-form-item">
					<label class="layui-form-label">'.$cname.'</label>
					<div class="layui-input-block">
						<input type="text" '.$idname.' value="'.$val.'" class="layui-input layui-input-inline">
						<input type="file" name="file" data-for="'.$fname.'" lay-type="file" lay-title="上传'.$cname.'" class="layui-upload-file">
					</div>
				</div>';
		}

		//单图片上传[更新:单图片预览/浏览服务器图片资源]
		elseif($field_type=='photo'){
			$html = '<div class="layui-form-item">
					<label class="layui-form-label">'.$cname.'</label>
					<div class="layui-input-block">
						<input type="text" '.$idname.' value="'.$val.'" class="layui-input layui-input-inline" onmouseover="javascript:document.preview.style.display=\'block\';"
						 onmouseout="javascript:document.preview.style.display=\'none\';">
						<input type="file" name="file" data-for="'.$fname.'" lay-type="images" lay-title="上传'.$cname.'" class="layui-upload-file">
						<input type="button"   data-for="'.$fname.'" lay-type="images" lay-title="浏览服务器资源"  class="layui-upload-file '.$fname.'" >
						<img src="'.$val.'" onerror="this.src=\'/public/admin/images/preview_one404.png\'"style="display:none;width: 300px;
    		height: 200px; position: absolute;z-index:100" name ="preview" >
					</div>
				</div>
			<script type="text/javascript">
				$(function(){
					$("body").on("click",".'.$fname.'",function(){
						var layer = layui.layer;
						layer.open({
							type:1,
							title:"浏览服务器资源",
							resize:false,
							area: ["1060px", "700px"],
							content:$(".img_resource")
						});
					});
				$(".'.$fname.'").off("click").on("click",function(){
					var $name = $(this).data("for");
					$(".img_resource img").off("click");
						$(".img_resource img").on("click",function(){
							$("#'.$fname.'").val($(this).data("id"));
							layer.closeAll();
						});
					});
				});
			</script>
				';


		}



		//多图片上传[新增:多个图片上传\修改时图片预览]
		elseif($field_type=='photos'){
				$images=preg_split("/[\s,;]+/",$val);
				$imageTag='';
					if($val){
						for($i=0;$i<sizeof($images);$i++){
							$imageTag.="<img style='border: 1px solid #CDCDB4;margin-right: 10px;padding: 10px;'src='".$images[$i]."' height='70' width='110'/><a href='javascript:;'><em data-url='/admin/content/img_del/id-".str_replace(".html","",explode("-", $_SERVER['PHP_SELF'])[3])."-pid-".str_replace(".html","",explode("-", $_SERVER['PHP_SELF'])[1])."-image_id-".$images[$i]."-fname-".$fname."' class='ajax-delete' style='position: absolute;margin-top: 0px;margin-left: -24px;color: #888;font-weight: bold;' title='确定要删除该图片'>×</em></a>";
						}
					}


			$html = '<style>body .demo-class .layui-layer-btn a{width:100px;height:50px;vertical-align:middle; text-align:center;padding-top:10px;}</style>
					<div class="layui-form-item">
					<label class="layui-form-label">'.$cname.'</label>
					<div class="layui-input-block" style="margin-bottom: 10px;">
						<input type="text" '.$idname.' value="'.$val.'" class="layui-input layui-input-inline">
						<input type="file" name="file[]"  data-for="'.$fname.'" data-imagetype="'.$fname.'-local-resource" lay-type="images" lay-title="上传'.$cname.'" class="layui-upload-file " multiple="multiple">
						<input type="button"  lay-type="images"  data-for="'.$fname.'" data-imagetype="'.$fname.'-web-resource" lay-title="浏览服务器资源"  class="layui-upload-file '.$fname.'" >
					</div>
					<div style="margin-left: 140px;">'.$imageTag.'</div>

				</div>


			<script type="text/javascript">
			var $imagetype = "";
			$("input").on("click",function(){
				$(this).data("imagetype");
				$imagetype = $(this).data("imagetype")

			});

				$(function(){
					//多图片上传
					$("#save-btn").on("click",function(){
						if($imagetype == "'.$fname.'-local-resource"){
							var list = $("#'.$fname.'").val().split(",");
							var arrs = list[0].split("/");
							for(var i=1;i<list.length;i++){
								list[i]="/"+arrs[1]+"/"+arrs[2]+"/"+arrs[3]+"/"+list[i];
							}
								$("#'.$fname.'").val(list);
						}
					});
				});

				$(function(){
					$("body").on("click",".'.$fname.'",function(){
						var layer = layui.layer;
						layer.open({
							type:1,
							title:"浏览服务器资源",
							resize:true,
							btn: ["确定"],
							yes: function(index, layero){
							    chkekboxchecked();
							  },
							skin: "demo-class",
							area: ["1060px", "700px"],
							content:$(".imgduotu_resource")
						});
					});
				});
				function  chkekboxchecked(){
					if($imagetype == "'.$fname.'-web-resource"){
						var sum="";
						$("input[name=\'image-checkbox\']:checked").each(function(){
							sum  +=$(this).val()+",";
						});
						sum = sum.substring(0,sum.length-1);
						$("#'.$fname.'").val(sum);
						layer.closeAll();
					}
				}
			</script>';
		}

		//日期选择器[更新:增加时间选择器]
		elseif($field_type=='addtime'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
							 <input type="text" '.$idname.' lay-verify="date" placeholder="yyyy-mm-dd hh:mm" autocomplete="off" class="layui-input" onclick="layui.laydate({elem: this,istime: true})" value="'.$val.'" style="width:242px">
						</div>
					</div>';
		}

		//内容编辑器
		elseif($field_type=='editor'){
			$html = '<div class="layui-form-item">
						<label class="layui-form-label">'.$cname.'</label>
						<div class="layui-input-block">
							<textarea '.$idname.' lang="editor" lay-verify="content" placeholder="在此输入备注内容" class="layui-textarea">'.$val.'</textarea>
						</div>
						<script>
						layui.use("layedit",function(){
							var layedit = layui.layedit;
							layedit.build("'.$fname.'",{
								tool: ["strong","italic","underline","del","|","left","center","right","|","link","unlink","image"],
								uploadImage: {
									url: "/admin/file/upload",
									type: "post"
								}
							});
						});
						</script>
					</div>';
		}
		//省市区选择器[新增:联动三级]
		elseif($field_type=='address'){
			$val=explode(" ",$val);
			$html = '<div class="layui-form-item" '.$idname.'   >
               				<label class=" layui-form-label">'.$cname.'</label>
                				<div class=" layui-input-inline">
                    				<select address="'.$fname.'" id = "'.$fname.'-province"   lay-filter="'.$fname.'-province" placeholder="'.$val[0].'">
                        				<option value="">请选择</option>
                    				</select>
                				</div>
                				<div class="layui-input-inline" >
                    				<select address="'.$fname.'" id = "'.$fname.'-city" lay-filter="'.$fname.'-city" placeholder="'.$val[1].'">
                        				<option value="">请选择</option>
                    				</select>
	                			</div>
	                			<div class="layui-input-inline" >
	                    			<select address="'.$fname.'" id = "'.$fname.'-area" ="'.$fname.'-area"  lay-filter="'.$fname.'-area" placeholder="'.$val[2].'">
	                        			<option value="">'.$val[2].'</option>
	                   			 	 </select>
	                			</div>
	                			<input type="hidden" name='.$fname.'  id="'.$fname.'-hidden_address"/>
	                			<script type="text/javascript" src="/public/admin/js/area.js"></script>
		<script>

		//初始数据
		var '.$fname.'address=[];
		var '.$fname.'array = [];
        var areaData = Area;
        var $form;
        var form;
        var $;
        layui.use([\'jquery\', \'form\'], function() {
            $ = layui.jquery;
            form = layui.form();
            $form = $(\'form\');
            '.$fname.'loadProvince();
        });
         //加载省数据
        function '.$fname.'loadProvince() {
            var proHtml = "<option>请选择</option>";
                for (var i = 0; i < areaData.length; i++) {
                    if(areaData[i].provinceName==$(\'#'.$fname.'-province\').attr("placeholder")){
                        province_id=areaData[i].provinceName+\'_\'+areaData[i].mallCityList.length+\'_\'+i;

                      proHtml += \'<option selected id="\' + areaData[i].provinceName + \'_\' + areaData[i].mallCityList.length + \'_\' + i + \'" value="\' + areaData[i].provinceName + \'">\' + areaData[i].provinceName + \'</option>\';
                     }
                     else{
                         proHtml += \'<option  id="\' + areaData[i].provinceName + \'_\' + areaData[i].mallCityList.length + \'_\' + i + \'" value="\' + areaData[i].provinceName  + \'">\' + areaData[i].provinceName + \'</option>\';
                }
            }

            //初始化省数据
            $form.find(\'select[id='.$fname.'-province]\').append(proHtml);

            form.render();
            form.on(\'select('.$fname.'-province)\', function(data) {
                $form.find(\'select[id='.$fname.'-area]\').html(\'<option value="">请选择县/区</option>\'
                	).parent().hide();
				'.$fname.'array[data.elem.id] = data.value;
				if(data.elem.id == \''.$fname.'-province\'){
        			'.$fname.'array[\''.$fname.'-city\'] ="";
        			'.$fname.'array[\''.$fname.'-area\'] ="";
    			}
    			'.$fname.'address = '.$fname.'array[\''.$fname.'-province\']+" ";
    			 $(\'#'.$fname.'-hidden_address\').val('.$fname.'address);


                var value = data.elem.selectedOptions["0"].id;
                var d = value.split(\'_\');
                var code = d[0];
                var count = d[1];
                var index = d[2];
                if (count > 0) {
                    '.$fname.'loadCity(areaData[index].mallCityList);
                } else {
                     $form.find(\'select[id='.$fname.'-city]\').parent().hide();
                }
            });
            if($(\'#'.$fname.'-province\').val!=\'请选择\'){
                var d =0;
                 d = province_id.split(\'_\');
                var code = d[0];
                var count = d[1];
                var index = d[2];
                if (count > 0) {
                    '.$fname.'loadCity(areaData[index].mallCityList);
                } else {
                    $form.find(\'select[id='.$fname.'-city]\').parent().hide();
                }
            }
        }
         //加载市数据
        function '.$fname.'loadCity(citys) {
            var cityHtml = "<option>请选择</option>";

                for (var i = 0; i < citys.length; i++) {
                    if($(\'#'.$fname.'-city\').attr("placeholder")==citys[i].cityName){
                        city_id=citys[i].cityName + \'_\' + citys[i].mallAreaList.length + \'_\' + i;
                         cityHtml += \'<option selected id="\' + citys[i].cityName + \'_\' + citys[i].mallAreaList.length + \'_\' + i + \'"  value="\' + citys[i].cityName+ \'">\' + citys[i].cityName + \'</option>\';
                    }else{
                        cityHtml += \'<option id="\' + citys[i].cityName + \'_\' + citys[i].mallAreaList.length + \'_\' + i + \'"  value="\' + citys[i].cityName+ \'">\' + citys[i].cityName + \'</option>\';

                    }

            }

            $form.find(\'select[id='.$fname.'-city]\').html(cityHtml).parent().show();
            form.render();

            form.on(\'select('.$fname.'-city)\', function(data) {
                var value = data.elem.selectedOptions["0"].id;
                var d = value.split(\'_\');

				'.$fname.'array[data.elem.id] = data.value;
				if(data.elem.id == \''.$fname.'-city\'){
       				 '.$fname.'array[\''.$fname.'-area\'] ="";
    			}
				'.$fname.'address = '.$fname.'array[\''.$fname.'-province\']+" "+'.$fname.'array[\''.$fname.'-city\']+" ";
				 $(\'#'.$fname.'-hidden_address\').val('.$fname.'address);

                var code = d[0];
                var count = d[1];
                var index = d[2];
                if (count > 0) {
                    '.$fname.'loadArea(citys[index].mallAreaList);
                } else {
                    $form.find(\'select[id='.$fname.'-area]\').parent().hide();
                }
            });
              if($(\'#'.$fname.'-city\').val!="请选择"){
                 var d = city_id.split(\'_\');
                var code = d[0];
                var count = d[1];
                var index = d[2];
                if (count > 0) {
                    '.$fname.'loadArea(citys[index].mallAreaList);
                } else {
                    $form.find(\'select[id='.$fname.'-area]\').parent().hide();
                }
            }
        }
         //加载县/区数据
        function '.$fname.'loadArea(areas) {
            var areaHtml = "<option>请选择</option>";

                for (var i = 0; i < areas.length; i++) {
                    if($(\'#'.$fname.'-area\').attr("placeholder")==areas[i].areaName ){

                        areaHtml += \'<option  selected value="\' + areas[i].areaName +\'">\' + areas[i].areaName + \'</option>\';
                    }else{
                         areaHtml += \'<option value="\' + areas[i].areaName + \'">\' + areas[i].areaName + \'</option>\';
                    }
            	}
            $form.find(\'select[id='.$fname.'-area]\').html(areaHtml).parent().show();
            form.render();
            form.on(\'select('.$fname.'-area)\', function(data) {
				'.$fname.'array[data.elem.id] = data.value;
				 '.$fname.'address = '.$fname.'array[\''.$fname.'-province\']+" "+'.$fname.'array[\''.$fname.'-city\']+" "+'.$fname.'array[\''.$fname.'-area\'];
    			 $(\'#'.$fname.'-hidden_address\').val('.$fname.'address);
            });
        }

         if($(\'#'.$fname.'-province\').attr("placeholder")!="请选择"){
        '.$fname.'array[\''.$fname.'-province\']=$(\'#'.$fname.'-province\').attr("placeholder");
        '.$fname.'array[\''.$fname.'-city\']=$(\'#'.$fname.'-city\').attr("placeholder");
        '.$fname.'array[\''.$fname.'-area\']=$(\'#'.$fname.'-area\').attr("placeholder");
         '.$fname.'address = '.$fname.'array[\''.$fname.'-province\']+" "+'.$fname.'array[\''.$fname.'-city\']+" "+'.$fname.'array[\''.$fname.'-area\'];
        $(\'#'.$fname.'-hidden_address\').val('.$fname.'address);
		}
        </script>
    				</div>';
		}
		return $html;
	}


//-----------------------------------------结束相关的控制器--------------------------------------------













//-----------------------------------------分类管理相关的控制器--------------------------------------------

	/**
	 * 内容分类管理
	 * @return [type] [description]
	 */
	public function category(){

		$pid = $this->checkPid();
		$list = $this->db->table('category as a')->field('*,(select count(*) from '.$this->db->pre('category').' where fid=a.id) as sub_number')->where('pid=? and fid=?',array($pid,$this->fid))->order('sort')->getlist();
		$this->view['list']=$list;
		$this->view('content_category.html');
	}

	/**
	 * 保存分类
	 * @return [type] [description]
	 */
	public function category_save(){
		$pid = $this->checkPid();
		$data = F::input('post.category_names');
		$data = str_replace(PHP_EOL,"\n",$data);
		$data = explode("\n", $data);
		if($data){
		 	foreach($data as $k=>$v){
		 	$v = trim($v);
		 		if($v){
		 			$max = $this->db->table('category')->where('pid=? and fid=?',array($pid,$this->fid))->max('sort');
					$max+=1;
					$this->ins('category',array(
		 			'name'=>$v,
		 			'pid'=>$pid,
		 			'fid'=>$this->fid,
					'sort'=>$max,
					));
				}
			}
		 	F::redirect('分类添加成功',$this->url('content/category',['pid'=>$pid,'fid'=>$this->fid]),2);
		}else{
		 	F::redirect('分类添加失败',$this->url('content/category',['pid'=>$pid,'fid'=>$this->fid]),2);
		}
	}
	/**
	 * 保存单个修改的分类
	 * @return [type] [description]
	 */
	public function category_save1(){
		$pid = $this->checkPid();
		$data = F::input('post.');
		$id = $data['id'];
		if($id>0){
			unset($data['id']);
			$this->db->table('category')->where('id=?',array($id))->update($data);
			F::redirect('修改成功',$this->url('content/category',['pid'=>$pid,'fid'=>$this->fid]),2);
		}
		else{
			F::redirect('修改失败，请稍后重试','',2);
		}
	}
	/**
	 * 移动分类
	 * @return [type] [description]
	 */
	public function category_move(){
		$pid = $this->checkPid();
		$action = F::input('get.action');
		$id = F::input_int('get.id');
		if($id>0){
			$max = $this->db->table('category')->where('pid=? and fid=?',array($pid,$this->fid))->max('sort');
			if($max>1){
				$thisSort = $this->val('category','sort','id='.$id);
				if($action=='up' && $thisSort==1){
					$str = '已经移到第一位';
				}
				elseif($action=='up' && $thisSort>1){//可以向上移动
					$preid = $this->val('category','id','pid='.$pid.' and fid='.$this->fid.' and sort='.($thisSort-1));
					$this->db->table('category')->where('id=?',array($id))->update([
						'sort'=>($thisSort-1)
					]);
					$this->db->table('category')->where('id=?',array($preid))->update([
						'sort'=>$thisSort
					]);
					$str = '操作成功';
				}
				elseif($action=='down' && $thisSort<$max){//可以向下移动
					$preid = $this->val('category','id','pid='.$pid.' and fid='.$this->fid.' and sort='.($thisSort+1));
					$this->db->table('category')->where('id=?',array($id))->update([
						'sort'=>($thisSort+1)
					]);
					$this->db->table('category')->where('id=?',array($preid))->update([
						'sort'=>$thisSort
					]);
					$str = '操作成功';
				}
				elseif($action=='down' && $thisSort==$max){//可以向下移动
					$str = '已经移到最后位';
				}
			}
			else{
				$str = '无需移动';
			}
		}
		F::redirect($str,$this->url('content/category',['pid'=>$pid,'fid'=>$this->fid]),2);
	}

	public function category_del(){
		$pid = $this->checkPid();
		$id = F::input_int('get.id');
		if($id>0){
			$count = $this->db->table('category')->where('fid=?',array($id))->count('id');
			if($count>0){
				F::redirect('分类下有'.$count.'条子分类数据，请从子分类删起','',2);
			}
			else{
				$this->del('category',$id);
				$this->reSort($pid,$this->fid,'category');
				F::redirect('分类删除成功',$this->url('content/category',['pid'=>$pid,'fid'=>$this->fid]),2);
			}
		}
	}

	/**
	 * 全部重新排序
	 * @return [type] [description]
	 */
	public function reSort($pid,$fid,$table){
		$sort_list = $this->db->table($table)->field('id,sort')->where('pid=? and fid=?',array($pid,$fid))->order('sort')->getlist();
		foreach($sort_list as $k=>$v){
			$this->db->table($table)->where('id=?',array($v['id']))->update(['sort'=>($k+1)]);
		}
	}

//-----------------------------------------结束分类管理--------------------------------------------
}
?>