<?php
//error_reporting(0);
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
if(defined('IN_CRONLITE'))return;
define('VERSION', '3065');
define('DB_VERSION', '2036');
define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');
define('PAYPAGE_ROOT', SYSTEM_ROOT.'pages/');
define('TEMPLATE_ROOT', ROOT.'template/');
define('PLUGIN_ROOT', ROOT.'plugins/');
date_default_timezone_set('Asia/Shanghai');
$date = date("Y-m-d H:i:s");
if(!function_exists("is_https")){
	function is_https() {
		if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443){
			return true;
		}elseif(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')){
			return true;
		}elseif(isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'){
			return true;
		}elseif(isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_EWS_CUSTOME_SCHEME']) && $_SERVER['HTTP_EWS_CUSTOME_SCHEME'] == 'https'){
			return true;
		}
		return false;
	}
}
if(!isset($nosession) || !$nosession){
    // 设置安全的 session cookie 参数
    // PHP 7.3.0 及以上版本支持 'samesite' 选项
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0, // 0 表示会话cookie，浏览器关闭时过期
            'path' => '/',   // 可用于整个域名
            'domain' => '',  // 当前域名，留空通常即可。对于更严格的控制，可以指定域名。
            'secure' => is_https(), // 仅在 HTTPS 连接时发送 cookie
            'httponly' => true,     // 禁止 JavaScript 访问 cookie
            'samesite' => 'Lax'     // 防止 CSRF 攻击, 可选 'Strict', 'Lax', 'None' (None需要Secure=true)
        ]);
    } else {
        // 对于 PHP < 7.3.0, samesite 需要通过 header() 单独设置，或者在 php.ini 中配置
        // 例如: session.cookie_samesite = "Lax"
        // 这里主要设置 httponly 和 secure，这些是早期版本支持的
        session_set_cookie_params(
            0, // lifetime
            '/', // path
            '',  // domain
            is_https(), // secure
            true // httponly
        );
        // 安全提示: 对于 PHP < 7.3 版本，SameSite 属性无法通过 session_set_cookie_params() 直接设置。
        // 建议升级 PHP 版本，或在 Web 服务器层面配置 SameSite 属性，或在 session_start() 后通过 header() 补充设置（可能不完全可靠）。
    }
    session_start();
}



$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].'/';

if(is_file(SYSTEM_ROOT.'360safe/360webscan.php')){//360网站卫士
//    require_once(SYSTEM_ROOT.'360safe/360webscan.php');
}

include_once(SYSTEM_ROOT."autoloader.php");
Autoloader::register();

if($is_defend){
	include_once(SYSTEM_ROOT."txprotect.php");
}

require ROOT.'config.php';
define('DBQZ', $dbconfig['dbqz']);

if(!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname'])//检测安装1
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="/install/">点此安装</a>';
exit();
}

$DB = new \lib\PdoHelper($dbconfig);

if($DB->query("select * from pre_config where 1")==FALSE)//检测安装2
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="/install/">点此安装</a>';
exit();
}


$CACHE=new \lib\Cache();
$conf=$CACHE->pre_fetch();
define('SYS_KEY', $conf['syskey']);
if(!$conf['localurl'])$conf['localurl'] = $siteurl;
$password_hash='!@#%!s!0';

if ($conf['version'] < DB_VERSION) {
    if (!$install) {
		header('Content-type:text/html;charset=utf-8');
        echo '请先完成网站升级！<a href="/install/update.php"><font color=red>点此升级</font></a>';
        exit;
    }
}

include_once(SYSTEM_ROOT."functions.php");
include_once(SYSTEM_ROOT."member.php");

require_once SYSTEM_ROOT."vendor/autoload.php";

if (!file_exists(ROOT.'install/install.lock') && file_exists(ROOT.'install/index.php')) {
	sysmsg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="/install/">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是安装保护文件，如果检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装你的网站。<br/><br/>');exit;
}

if($conf['cdnpublic']==1){
	$cdnpublic = '//lib.baomitu.com/';
}elseif($conf['cdnpublic']==2){
	$cdnpublic = 'https://cdn.bootcdn.net/ajax/libs/';
}elseif($conf['cdnpublic']==4){
	$cdnpublic = '//lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/';
}else{
	$cdnpublic = '//cdn.staticfile.org/';
}
?>