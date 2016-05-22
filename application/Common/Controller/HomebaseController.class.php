<?php
namespace Common\Controller;

use Common\Controller\AppframeController;
use LaneWeChat\Core\WeChatOAuth;

class HomebaseController extends AppframeController
{

    private $uri;
    private $openid;
    private $accessToken;

    public function __construct()
    {
        $this->set_action_success_error_tpl();
        parent::__construct();
    }

    function _initialize()
    {
        parent::_initialize();
        $this->uri = preg_replace('/\/\?\/$/', '', "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $site_options = get_site_options();
        $this->assign($site_options);

        $this->getOpenId();

        // 绑定微信用户信息
        $this->getUserInfo();

    }

    /**
     * 检查用户登录
     */
    protected function check_login()
    {
        if (!isset($_SESSION["user"])) {
            $this->error('您还没有登录！', __ROOT__ . "/");
        }

    }

    /**
     * 检查用户状态
     */
    protected function  check_user()
    {
        $user_status = M('Users')->where(array("id" => sp_get_current_userid()))->getField("user_status");
        if ($user_status == 2) {
            $this->error('您还没有激活账号，请激活后再使用！', U("user/login/active"));
        }

        if ($user_status == 0) {
            $this->error('此账号已经被禁止使用，请联系管理员！', __ROOT__ . "/");
        }
    }

    /**
     * 发送注册激活邮件
     */
    protected function _send_to_active()
    {
        $option = M('Options')->where(array('option_name' => 'member_email_active'))->find();
        if (!$option) {
            $this->error('网站未配置账号激活信息，请联系网站管理员');
        }
        $options = json_decode($option['option_value'], true);
        //邮件标题
        $title = $options['title'];
        $uid = $_SESSION['user']['id'];
        $username = $_SESSION['user']['user_login'];

        $activekey = md5($uid . time() . uniqid());
        $users_model = M("Users");

        $result = $users_model->where(array("id" => $uid))->save(array("user_activation_key" => $activekey));
        if (!$result) {
            $this->error('激活码生成失败！');
        }
        //生成激活链接
        $url = U('user/register/active', array("hash" => $activekey), "", true);
        //邮件内容
        $template = $options['template'];
        $content = str_replace(array('http://#link#', '#username#'), array($url, $username), $template);

        $send_result = sp_send_email($_SESSION['user']['user_email'], $title, $content);

        if ($send_result['error']) {
            $this->error('激活邮件发送失败，请尝试登录后，手动发送激活邮件！');
        }
    }

    /**
     * 加载模板和页面输出 可以返回输出内容
     * @access public
     * @param string $templateFile 模板文件名
     * @param string $charset 模板输出字符集
     * @param string $contentType 输出类型
     * @param string $content 模板输出内容
     * @return mixed
     */
    public function display($templateFile = '', $charset = '', $contentType = '', $content = '', $prefix = '')
    {
        //echo $this->parseTemplate($templateFile);
        parent::display($this->parseTemplate($templateFile), $charset, $contentType);
    }

    /**
     * 获取输出页面内容
     * 调用内置的模板引擎fetch方法，
     * @access protected
     * @param string $templateFile 指定要调用的模板文件
     * 默认为空 由系统自动定位模板文件
     * @param string $content 模板输出内容
     * @param string $prefix 模板缓存前缀*
     * @return string
     */
    public function fetch($templateFile = '', $content = '', $prefix = '')
    {
        $templateFile = empty($content) ? $this->parseTemplate($templateFile) : '';
        return parent::fetch($templateFile, $content, $prefix);
    }

    /**
     * 自动定位模板文件
     * @access protected
     * @param string $template 模板文件规则
     * @return string
     */
    public function parseTemplate($template = '')
    {

        $tmpl_path = C("SP_TMPL_PATH");
        define("SP_TMPL_PATH", $tmpl_path);
        // 获取当前主题名称
        $theme = C('SP_DEFAULT_THEME');
        if (C('TMPL_DETECT_THEME')) {// 自动侦测模板主题
            $t = C('VAR_TEMPLATE');
            if (isset($_GET[$t])) {
                $theme = $_GET[$t];
            } elseif (cookie('think_template')) {
                $theme = cookie('think_template');
            }
            if (!file_exists($tmpl_path . "/" . $theme)) {
                $theme = C('SP_DEFAULT_THEME');
            }
            cookie('think_template', $theme, 864000);
        }

        $theme_suffix = "";

        if (C('MOBILE_TPL_ENABLED') && sp_is_mobile()) {//开启手机模板支持

            if (C('LANG_SWITCH_ON', null, false)) {
                if (file_exists($tmpl_path . "/" . $theme . "_mobile_" . LANG_SET)) {//优先级最高
                    $theme_suffix = "_mobile_" . LANG_SET;
                } elseif (file_exists($tmpl_path . "/" . $theme . "_mobile")) {
                    $theme_suffix = "_mobile";
                } elseif (file_exists($tmpl_path . "/" . $theme . "_" . LANG_SET)) {
                    $theme_suffix = "_" . LANG_SET;
                }
            } else {
                if (file_exists($tmpl_path . "/" . $theme . "_mobile")) {
                    $theme_suffix = "_mobile";
                }
            }
        } else {
            $lang_suffix = "_" . LANG_SET;
            if (C('LANG_SWITCH_ON', null, false) && file_exists($tmpl_path . "/" . $theme . $lang_suffix)) {
                $theme_suffix = $lang_suffix;
            }
        }

        $theme = $theme . $theme_suffix;

        C('SP_DEFAULT_THEME', $theme);

        $current_tmpl_path = $tmpl_path . $theme . "/";
        // 获取当前主题的模版路径
        define('THEME_PATH', $current_tmpl_path);

        C("TMPL_PARSE_STRING.__TMPL__", __ROOT__ . "/" . $current_tmpl_path);

        C('SP_VIEW_PATH', $tmpl_path);
        C('DEFAULT_THEME', $theme);

        define("SP_CURRENT_THEME", $theme);

        if (is_file($template)) {
            return $template;
        }
        $depr = C('TMPL_FILE_DEPR');
        $template = str_replace(':', $depr, $template);

        // 获取当前模块
        $module = MODULE_NAME;
        if (strpos($template, '@')) { // 跨模块调用模版文件
            list($module, $template) = explode('@', $template);
        }


        // 分析模板文件规则
        if ('' == $template) {
            // 如果模板文件名为空 按照默认规则定位
            $template = "/" . CONTROLLER_NAME . $depr . ACTION_NAME;
        } elseif (false === strpos($template, '/')) {
            $template = "/" . CONTROLLER_NAME . $depr . $template;
        }

        $file = sp_add_template_file_suffix($current_tmpl_path . $module . $template);
        $file = str_replace("//", '/', $file);
        if (!file_exists_case($file)) E(L('_TEMPLATE_NOT_EXIST_') . ':' . $file);
        return $file;
    }

    /**
     * 设置错误，成功跳转界面
     */
    private function set_action_success_error_tpl()
    {
        $theme = C('SP_DEFAULT_THEME');
        if (C('TMPL_DETECT_THEME')) {// 自动侦测模板主题
            if (cookie('think_template')) {
                $theme = cookie('think_template');
            }
        }
        //by ayumi手机提示模板
        $tpl_path = '';
        if (C('MOBILE_TPL_ENABLED') && sp_is_mobile() && file_exists(C("SP_TMPL_PATH") . "/" . $theme . "_mobile")) {//开启手机模板支持
            $theme = $theme . "_mobile";
            $tpl_path = C("SP_TMPL_PATH") . $theme . "/";
        } else {
            $tpl_path = C("SP_TMPL_PATH") . $theme . "/";
        }

        //by ayumi手机提示模板
        $defaultjump = THINK_PATH . 'Tpl/dispatch_jump.tpl';
        $action_success = sp_add_template_file_suffix($tpl_path . C("SP_TMPL_ACTION_SUCCESS"));
        $action_error = sp_add_template_file_suffix($tpl_path . C("SP_TMPL_ACTION_ERROR"));
        if (file_exists_case($action_success)) {
            C("TMPL_ACTION_SUCCESS", $action_success);
        } else {
            C("TMPL_ACTION_SUCCESS", $defaultjump);
        }

        if (file_exists_case($action_error)) {
            C("TMPL_ACTION_ERROR", $action_error);
        } else {
            C("TMPL_ACTION_ERROR", $defaultjump);
        }
    }

    /**
     * 判断是否在微信浏览器
     * @return type
     */
    final public static function inWechat()
    {
//		return strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false;
        return true;
    }

    /**
     * 获取用户openid
     * @param type $both 是否同时获取accesstoken
     * @return boolean | object
     */
    final public function getOpenId($redirect_uri = false)
    {
        if (isset($_SESSION['uopenid']) || isset($_SESSION['uaccesstoken'])) {
            $Openid = $_SESSION['uopenid'];
            $AccessToken = $_SESSION['uaccesstoken'];

            $this->openid = $Openid;
            $this->accessToken = $AccessToken;

            $this->refreshOpenId($Openid, $AccessToken);
        } else {
            if ($this->inWechat()) {
                $redirect_uri = !$redirect_uri ? $this->uri : $redirect_uri;
                $AccessCode = $this->getAccessCode($redirect_uri);
                if ($AccessCode !== FALSE) {
                    // 获取到accesstoken和openid
                    $Result = WeChatOAuth::getAccessTokenAndOpenId($AccessCode);
                    $Openid = $Result["openid"];
                    $AccessToken = $Result["access_token"];
                    // cookie持久1小时
                    $this->refreshOpenId($Openid, $AccessToken);
                    unset($Result);
                }
                unset($AccessCode);
            } else {
                return false;
            }
        }
        return $Openid;
    }

    final public function getUserInfo()
    {
        $we_users_model = D("Common/WeUsers");

        if (isset($_SESSION["user"])) {
            return $_SESSION["user"];
        } else {

            // 如果数据库中也没有，则获取
            $user_info = $we_users_model->where(array("openid" => $this->openid))->limit(0)->select();
            if (empty($user_info)) {
                $user_info = WeChatOAuth::getUserInfo($this->accessToken, $this->openid);
                if (empty($user_info)) {
                    return false;
                }

                $_SESSION["user"] = $user_info;
                $UserInfo["privilege"] = serialize($user_info["privilege"]);

                $UserInfo['last_login_time'] = date("Y-m-d H:i:s");
                $UserInfo['last_login_ip'] = get_client_ip(0,true);

                $we_users_model->create($user_info);
            } else {
                $_SESSION["user"] = $user_info;
            }
        }
    }

    private function getAccessCode($redirect_uri)
    {
        $code = I("get.code");
        if (empty($code)) {
            WeChatOAuth::getCode($redirect_uri, $state = 1, $scope = 'snsapi_userinfo');
        } else {
            // 授权成功 返回 access_token 票据
            return $code;
        }
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @param int $exp
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function sCookie($key, $value, $exp = 36000, $path = NULL, $domain = NULL)
    {
        return setcookie($key, $value, $this->now + $exp, $path, $domain);
    }

    /**
     * 持久cookie
     * @param type $Openid
     * @param type $AccessToken
     */
    private function refreshOpenId($Openid, $AccessToken)
    {
        $_SESSION["uopenid"] = $Openid;
        $_SESSION['uaccesstoken'] = $AccessToken;
    }


}