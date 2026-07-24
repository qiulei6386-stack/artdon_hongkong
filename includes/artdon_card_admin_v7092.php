<?php
/** Shared database/schema helpers for V7.0.9.2 admin, installer and diagnostics. */
declare(strict_types=1);

if (!function_exists('artdon_card_admin_v7092_root')) {
function artdon_card_admin_v7092_root(): string { return dirname(__DIR__); }
}
if (!function_exists('artdon_card_admin_v7092_include')) {
function artdon_card_admin_v7092_include(string $file): void { if (!is_file($file)) return; ob_start(); try { include_once $file; } catch (Throwable $e) {} ob_end_clean(); }
}
if (!function_exists('artdon_card_admin_v7092_pdo')) {
function artdon_card_admin_v7092_pdo(): PDO {
    static $pdo=null;if($pdo instanceof PDO)return $pdo;$root=artdon_card_admin_v7092_root();
    foreach([$root.'/includes/bootstrap.php',$root.'/config.php',$root.'/db.php',$root.'/database.php',$root.'/inc/config.php',$root.'/includes/config.php',$root.'/admin/config.php',$root.'/api/config.php'] as $f)artdon_card_admin_v7092_include($f);
    if(function_exists('web_db')){try{$e=null;$x=web_db($e);if($x instanceof PDO)$pdo=$x;}catch(Throwable $e){}}
    if(!$pdo){foreach(['pdo','db','dbh'] as $k){if(isset($GLOBALS[$k])&&$GLOBALS[$k] instanceof PDO){$pdo=$GLOBALS[$k];break;}}}
    if(!$pdo){foreach(['get_pdo','get_db','database'] as $fn){if(function_exists($fn)){try{$x=$fn();if($x instanceof PDO){$pdo=$x;break;}}catch(Throwable $e){}}}}
    if(!$pdo&&function_exists('db')){try{$x=db();if($x instanceof PDO)$pdo=$x;}catch(Throwable $e){}}
    if(!$pdo){
        $host=defined('DB_HOST')?(string)DB_HOST:(defined('MYSQL_HOST')?(string)MYSQL_HOST:(string)($GLOBALS['db_host']??$GLOBALS['host']??'localhost'));
        $name=defined('DB_NAME')?(string)DB_NAME:(defined('MYSQL_DATABASE')?(string)MYSQL_DATABASE:(string)($GLOBALS['db_name']??$GLOBALS['database']??'artdon_web'));
        $user=defined('DB_USER')?(string)DB_USER:(defined('MYSQL_USER')?(string)MYSQL_USER:(string)($GLOBALS['db_user']??$GLOBALS['user']??''));
        $pass=defined('DB_PASS')?(string)DB_PASS:(defined('MYSQL_PASSWORD')?(string)MYSQL_PASSWORD:(string)($GLOBALS['db_pass']??$GLOBALS['password']??''));
        $port=defined('DB_PORT')?(int)DB_PORT:3306;if($user==='')throw new RuntimeException('未识别香港官网数据库账号。');
        $pdo=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();if(strcasecmp($db,'artdon_web')!==0)throw new RuntimeException('当前数据库为 '.($db?:'未知').'，不是香港官网 artdon_web。');
    return $pdo;
}}
if (!function_exists('artdon_card_admin_v7092_defaults')) {
function artdon_card_admin_v7092_defaults(): array { require_once __DIR__.'/artdon_card_runtime_v7092.php'; return artdon_v7092_defaults(); }
}
if (!function_exists('artdon_card_admin_v7092_defs')) {
function artdon_card_admin_v7092_defs(): array { return [
 'font_scale_percent'=>['全部卡片字体总缩放','global',55,150,'%；先调这个最直观'],
 'series_title_font_size'=>['系列卡片标题','series',8,80,'px'], 'series_subtitle_font_size'=>['系列卡片副标题','series',8,60,'px'],
 'series_spec_label_font_size'=>['系列参数名称','series',8,60,'px'], 'series_spec_value_font_size'=>['系列参数内容','series',8,60,'px'], 'series_tag_font_size'=>['系列标签','series',8,40,'px；Track / Grille / Visual comfort 保留'],
 'product_title_font_size'=>['具体产品标题','product',8,80,'px'], 'product_subtitle_font_size'=>['具体产品副标题','product',8,60,'px'],
 'product_spec_label_font_size'=>['具体产品参数名称','product',8,60,'px'], 'product_spec_value_font_size'=>['具体产品参数内容','product',8,60,'px'], 'product_tag_font_size'=>['具体产品标签','product',8,40,'px'],
 'description_font_size'=>['描述文字','shared',8,60,'px'], 'meta_font_size'=>['备注 / 小字','shared',8,40,'px'], 'family_heading_font_size'=>['产品大类标题','shared',12,72,'px'],
 'badge_font_size'=>['NEW / ★ 标识字号','badge',8,36,'px'], 'badge_top'=>['标识距图片上边','badge',0,300,'px'], 'badge_left'=>['标识距图片左边','badge',0,300,'px'], 'badge_radius'=>['标识圆角','badge',0,999,'999 = 胶囊形'],
 'card_width'=>['卡片宽度','optional',120,1000,'留空 = 完全沿用 V7.0.8'], 'card_min_height'=>['卡片最小高度','optional',80,1600,'留空 = 完全沿用 V7.0.8'], 'image_subject_scale'=>['图片主体缩放','optional',20,150,'留空 = 完全沿用 V7.0.8，不硬放大'],
];}}
if (!function_exists('artdon_card_admin_v7092_ensure')) {
function artdon_card_admin_v7092_ensure(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `artdon_card_settings` (`setting_key` varchar(80) NOT NULL,`setting_value` varchar(255) NOT NULL DEFAULT '',`setting_label` varchar(120) NOT NULL DEFAULT '',`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`setting_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `artdon_card_flags` (`id` int(11) NOT NULL AUTO_INCREMENT,`item_type` varchar(20) NOT NULL DEFAULT 'product',`item_id` varchar(120) NOT NULL DEFAULT '',`item_name` varchar(255) NOT NULL DEFAULT '',`badge_type` varchar(20) NOT NULL DEFAULT 'none',`badge_text` varchar(40) NOT NULL DEFAULT '',`enabled` tinyint(1) NOT NULL DEFAULT '1',`note` varchar(255) NOT NULL DEFAULT '',`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_item_type_id` (`item_type`,`item_id`),KEY `idx_item_name` (`item_name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try{foreach($pdo->query('SHOW INDEX FROM artdon_card_flags')->fetchAll() as $idx){if(($idx['Key_name']??'')==='uniq_item_type_id'&&(int)($idx['Non_unique']??1)===0)$pdo->exec('ALTER TABLE artdon_card_flags DROP INDEX uniq_item_type_id');}}catch(Throwable $e){}
    $defaults=artdon_card_admin_v7092_defaults();$defs=artdon_card_admin_v7092_defs();
    // Old V7.0.9/V7.0.9.1 values are migrated once into the separated series/product fields.
    $old=[];try{foreach($pdo->query('SELECT setting_key,setting_value FROM artdon_card_settings')->fetchAll() as $r)$old[(string)$r['setting_key']]=(string)$r['setting_value'];}catch(Throwable $e){}
    if(isset($old['title_font_size'])){$defaults['series_title_font_size']=$old['title_font_size'];$defaults['product_title_font_size']=max(8,(float)$old['title_font_size']-2).'';}
    if(isset($old['subtitle_font_size'])){$defaults['series_subtitle_font_size']=$old['subtitle_font_size'];$defaults['product_subtitle_font_size']=max(8,(float)$old['subtitle_font_size']-1).'';}
    if(isset($old['spec_label_font_size'])){$defaults['series_spec_label_font_size']=$old['spec_label_font_size'];$defaults['product_spec_label_font_size']=$old['spec_label_font_size'];}
    if(isset($old['spec_value_font_size'])){$defaults['series_spec_value_font_size']=$old['spec_value_font_size'];$defaults['product_spec_value_font_size']=$old['spec_value_font_size'];}
    if(isset($old['tag_font_size'])){$defaults['series_tag_font_size']=$old['tag_font_size'];$defaults['product_tag_font_size']=$old['tag_font_size'];}
    $st=$pdo->prepare('INSERT IGNORE INTO artdon_card_settings(setting_key,setting_value,setting_label) VALUES(?,?,?)');
    foreach($defaults as $k=>$v){$label=$defs[$k][0]??$k;$st->execute([$k,(string)$v,$label]);}
    $pdo->exec("INSERT INTO artdon_card_settings(setting_key,setting_value,setting_label) VALUES('hide_view_details','1','固定隐藏 View Details') ON DUPLICATE KEY UPDATE setting_value='1'");
    $pdo->exec("INSERT INTO artdon_card_settings(setting_key,setting_value,setting_label) VALUES('hide_info_icon','1','固定隐藏圆形 i / 感叹号') ON DUPLICATE KEY UPDATE setting_value='1'");
}}
if (!function_exists('artdon_card_admin_v7092_load')) {
function artdon_card_admin_v7092_load(PDO $pdo): array {$a=artdon_card_admin_v7092_defaults();foreach($pdo->query('SELECT setting_key,setting_value FROM artdon_card_settings')->fetchAll() as $r)$a[(string)$r['setting_key']]=(string)$r['setting_value'];$a['hide_view_details']='1';$a['hide_info_icon']='1';return $a;}
}
if (!function_exists('artdon_card_admin_v7092_catalog')) {
function artdon_card_admin_v7092_catalog(PDO $pdo): array {
    $root=artdon_card_admin_v7092_root();artdon_card_admin_v7092_include($root.'/includes/product_hierarchy.php');$out=[];
    if(function_exists('web_product_fetch_all')){try{foreach(web_product_fetch_all($pdo,true) as $s){$sid=(int)($s['id']??0);$slug=trim((string)($s['slug']??''));$name=trim((string)($s['series_name']??$s['name']??''));if($name!=='')$out[]=['type'=>'series','id'=>$slug!==''?$slug:(string)$sid,'name'=>$name,'label'=>'系列｜'.$name];if($sid>0&&function_exists('web_product_variants')){foreach(web_product_variants($pdo,$sid,true) as $v){$vid=(int)($v['id']??0);$vslug=trim((string)($v['slug']??''));$vname=trim((string)($v['name']??$v['model_code']??''));$model=trim((string)($v['model_code']??''));if($vname!=='')$out[]=['type'=>'product','id'=>$vslug!==''?$vslug:(string)$vid,'name'=>$vname,'label'=>'产品｜'.$name.' / '.$vname.($model!==''?' ['.$model.']':'')];}}}}catch(Throwable $e){}}
    return array_slice($out,0,5000);
}}
