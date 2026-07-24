<?php
/**
 * Artdon Lighting 官网端 - 命名中心实时同步接口 V1.3
 * 放到香港官网根目录：/www/wwwroot/43.132.210.162/website_naming_sync_api.php
 * V1.3：补充开孔/外径/长宽高字段识别，供命名中心统一尺寸格式。
 */
declare(strict_types=1);
@ini_set('display_errors','0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');
header('Content-Type: application/json; charset=utf-8');

function ws_json($data, bool $ok=true, string $msg=''): void {
    echo json_encode(array('ok'=>$ok,'msg'=>$msg,'data'=>$data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ws_s($v, int $max=0): string {
    $v = trim((string)($v ?? ''));
    if ($max>0) return function_exists('mb_substr') ? mb_substr($v,0,$max,'UTF-8') : substr($v,0,$max);
    return $v;
}
function ws_config_paths(): array {
    return array_unique(array_filter(array(
        __DIR__.'/config.php',
        __DIR__.'/db.php',
        __DIR__.'/database.php',
        __DIR__.'/conn.php',
        __DIR__.'/connect.php',
        __DIR__.'/includes/config.php',
        __DIR__.'/includes/db.php',
        __DIR__.'/include/config.php',
        __DIR__.'/inc/config.php',
        __DIR__.'/admin/config.php',
        __DIR__.'/admin/db.php',
        __DIR__.'/admin/includes/config.php',
        __DIR__.'/admin/inc/config.php',
    ), 'is_file'));
}
function ws_flatten_array(array $arr, string $prefix='', array &$out=array()): array {
    foreach($arr as $k=>$v){
        $key = $prefix==='' ? (string)$k : $prefix.'_'.(string)$k;
        if(is_array($v)) ws_flatten_array($v, $key, $out);
        elseif(is_scalar($v) && ws_s($v)!=='') $out[$key]=ws_s($v);
    }
    return $out;
}
function ws_load_config_context(): array {
    $vars = array();
    foreach (ws_config_paths() as $cfg) {
        try {
            $ret = require_once $cfg;
            if (is_array($ret)) {
                $vars['return_config'] = $ret;
                $flat = array(); ws_flatten_array($ret, '', $flat);
                foreach($flat as $k=>$v) $vars[$k]=$v;
            }
            foreach (get_defined_vars() as $k=>$v) {
                if (in_array($k, array('vars','cfg','k','v','ret','flat'), true)) continue;
                $vars[$k] = $v;
                if (is_array($v)) { $flat=array(); ws_flatten_array($v, (string)$k, $flat); foreach($flat as $fk=>$fv) $vars[$fk]=$fv; }
            }
        } catch (Throwable $e) {}
    }
    foreach($GLOBALS as $gk=>$gv){
        if(is_array($gv)) { $flat=array(); ws_flatten_array($gv, (string)$gk, $flat); foreach($flat as $fk=>$fv) if(!isset($vars[$fk])) $vars[$fk]=$fv; }
    }
    return $vars;
}
function ws_cfg_const_env(array $names, string $default=''): string {
    foreach($names as $n){
        if(defined($n)){ $v=constant($n); if(is_scalar($v)&&ws_s($v)!=='') return ws_s($v); }
        $e=getenv($n); if($e!==false&&ws_s($e)!=='') return ws_s($e);
    }
    return $default;
}
function ws_var_first(array $vars, array $names, string $default=''): string {
    foreach($names as $n){
        if(array_key_exists($n,$vars) && is_scalar($vars[$n]) && ws_s($vars[$n])!=='') return ws_s($vars[$n]);
        if(array_key_exists(strtolower($n),$vars) && is_scalar($vars[strtolower($n)]) && ws_s($vars[strtolower($n)])!=='') return ws_s($vars[strtolower($n)]);
    }
    foreach($names as $n){
        if(array_key_exists($n,$GLOBALS) && is_scalar($GLOBALS[$n]) && ws_s($GLOBALS[$n])!=='') return ws_s($GLOBALS[$n]);
        if(array_key_exists(strtolower($n),$GLOBALS) && is_scalar($GLOBALS[strtolower($n)]) && ws_s($GLOBALS[strtolower($n)])!=='') return ws_s($GLOBALS[strtolower($n)]);
    }
    return $default;
}
function ws_db() {
    $vars = ws_load_config_context();
    foreach(array('pdo','db','conn','connection','dbh','web_pdo') as $name){
        if(isset($vars[$name]) && $vars[$name] instanceof PDO) { $vars[$name]->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $vars[$name]->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC); return $vars[$name]; }
        if(isset($GLOBALS[$name]) && $GLOBALS[$name] instanceof PDO) { $GLOBALS[$name]->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $GLOBALS[$name]->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC); return $GLOBALS[$name]; }
        if(class_exists('mysqli')){
            if(isset($vars[$name]) && $vars[$name] instanceof mysqli) { @$vars[$name]->set_charset('utf8mb4'); return $vars[$name]; }
            if(isset($GLOBALS[$name]) && $GLOBALS[$name] instanceof mysqli) { @$GLOBALS[$name]->set_charset('utf8mb4'); return $GLOBALS[$name]; }
        }
    }
    foreach(array('db','get_pdo','pdo','web_pdo','get_db','database','connect_db') as $fn){
        if(function_exists($fn)){
            try{
                $x=$fn();
                if($x instanceof PDO){ $x->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $x->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC); return $x; }
                if(class_exists('mysqli') && $x instanceof mysqli){ @$x->set_charset('utf8mb4'); return $x; }
            }catch(Throwable $e){}
        }
    }
    $host = ws_cfg_const_env(array('DB_HOST','MYSQL_HOST','WEB_DB_HOST','DATABASE_HOST','SQL_HOST','db_host','DB_SERVER','MYSQL_SERVER'),'');
    $db = ws_cfg_const_env(array('DB_NAME','MYSQL_DB','MYSQL_DATABASE','WEB_DB_NAME','DATABASE_NAME','DB_DATABASE','db_name','database_name','DB_DATABASE_NAME'),'');
    $user = ws_cfg_const_env(array('DB_USER','MYSQL_USER','MYSQL_USERNAME','WEB_DB_USER','DATABASE_USER','DB_USERNAME','db_user','database_user'),'');
    $pass = ws_cfg_const_env(array('DB_PASS','MYSQL_PASS','MYSQL_PASSWORD','WEB_DB_PASS','DATABASE_PASS','DB_PASSWORD','DB_PWD','db_pass','database_password'),'');
    $port = ws_cfg_const_env(array('DB_PORT','MYSQL_PORT','WEB_DB_PORT','DATABASE_PORT'),'3306');
    if($host==='') $host = ws_var_first($vars, array('DB_HOST','MYSQL_HOST','WEB_DB_HOST','host','hostname','db_host','mysql_host','servername','server','dbHost','database_host','db_hostname','config_db_host','config_database_host','database_hostname'), '127.0.0.1');
    if($db==='') $db = ws_var_first($vars, array('DB_NAME','MYSQL_DB','MYSQL_DATABASE','WEB_DB_NAME','database','dbname','db_name','dbName','mysql_db','db_database','database_name','config_db_name','config_database','config_database_name','db_database_name'), 'artdon_web');
    if($user==='') $user = ws_var_first($vars, array('DB_USER','MYSQL_USER','MYSQL_USERNAME','WEB_DB_USER','user','username','db_user','dbuser','dbUser','mysql_user','db_username','database_user','config_db_user','config_database_user'), '');
    if($pass==='') $pass = ws_var_first($vars, array('DB_PASS','MYSQL_PASS','MYSQL_PASSWORD','WEB_DB_PASS','pass','password','db_pass','dbpass','dbPassword','mysql_pass','db_password','database_password','config_db_pass','config_database_password','pwd'), '');
    $port = (int)(ws_var_first($vars, array('DB_PORT','MYSQL_PORT','WEB_DB_PORT','port','db_port','mysql_port'), $port ?: '3306'));
    if($db===''||$user==='') {
        $tried = array_map('basename', ws_config_paths());
        throw new RuntimeException('官网数据库配置不完整：未识别到数据库名或用户名；已尝试 '.implode(' / ', $tried));
    }
    return new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8mb4'));
}
function ws_query_all($db, string $sql, array $args=array()): array {
    if($db instanceof PDO){ $st=$db->prepare($sql); $st->execute($args); return $st->fetchAll() ?: array(); }
    if(class_exists('mysqli') && $db instanceof mysqli){
        if($args){ foreach($args as $a){ $sql=preg_replace('/\?/', "'".$db->real_escape_string((string)$a)."'", $sql, 1); } }
        $rs=$db->query($sql); if(!$rs) return array(); $out=array(); while($r=$rs->fetch_assoc()) $out[]=$r; return $out;
    }
    return array();
}
function ws_query_one($db, string $sql, array $args=array()) {
    $r=ws_query_all($db,$sql,$args); return $r ? reset($r) : null;
}
function ws_current_db_name($db): string {
    $r=ws_query_one($db,'SELECT DATABASE() AS db'); return is_array($r)?ws_s($r['db']??''):'';
}
function ws_table_exists($db, string $table): bool {
    $r=ws_query_one($db,'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',array($table));
    return (int)($r['c']??0)>0;
}
function ws_cols($db, string $table): array {
    $rows=ws_query_all($db,'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',array($table));
    return array_map(function($r){return (string)($r['COLUMN_NAME']??'');},$rows);
}
function ws_all_tables($db): array {
    $rows=ws_query_all($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME ASC');
    return array_map(function($r){return (string)($r['TABLE_NAME']??'');},$rows);
}
function ws_pick(array $r, array $keys): string { foreach($keys as $k){ if(isset($r[$k]) && is_scalar($r[$k]) && ws_s($r[$k])!=='') return ws_s($r[$k]); } foreach($keys as $k){ foreach($r as $rk=>$rv){ if(strtolower((string)$rk)===strtolower((string)$k) && is_scalar($rv) && ws_s($rv)!=='') return ws_s($rv); } } return ''; }
function ws_abs(string $p): string { $p=ws_s($p,500); if($p==='')return ''; if(preg_match('#^https?://#i',$p))return $p; if(strpos($p,'//')===0)return 'https:'.$p; $base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'artdonlighting.com'); return rtrim($base,'/').'/'.ltrim($p,'/'); }
function ws_model_clean(string $s): string { $s=strtoupper(trim($s)); $s=str_replace(array('．','。','-',' '),array('.','.','',''),$s); return preg_replace('/[^A-Z0-9.]+/','',$s); }
function ws_candidate_tables($db): array {
    $preferred = array('website_naming_products','website_products','web_products','artdon_products','products','product','product_list','product_models','web_product_models','website_product_models','product_sizes','website_product_sizes','product_variants','product_skus');
    $out=array(); foreach($preferred as $t) if(ws_table_exists($db,$t)) $out[]=$t;
    foreach(ws_all_tables($db) as $t){
        if(in_array($t,$out,true)) continue;
        $cols=ws_cols($db,$t); $joined=strtolower(implode(' ', $cols).' '.$t);
        if((strpos($joined,'model')!==false || strpos($joined,'sku')!==false || strpos($joined,'code')!==false) && (strpos($joined,'product')!==false || strpos($joined,'size')!==false || strpos($joined,'series')!==false)) $out[]=$t;
    }
    return $out;
}
function ws_export_rows($db): array {
    $out=array();
    $modelNames = array('model_no','model','sku','code','product_model','product_code','item_model','model_code','product_sku','item_no','number');
    foreach(ws_candidate_tables($db) as $t){
        $cols=ws_cols($db,$t); if(!$cols) continue;
        $modelCols=array_values(array_intersect($modelNames,$cols));
        if(!$modelCols){
            foreach($cols as $c){ if(preg_match('/(model|sku|product.*code|item.*no)/i',$c)) $modelCols[]=$c; }
        }
        if(!$modelCols) continue;
        $allow='/^(id|product_id|size_id|variant_id|sku_id|model_no|model|sku|code|product_model|product_code|item_model|model_code|product_sku|item_no|number|name|title|product_name|series|series_name|category|category_name|type_name|product_type|lamp_type|slug|url|link|image|image_path|image_url|main_image|cover_image|cover_url|drawing|drawing_path|drawing_url|dimension|dimension_url|dimension_image|size_image|size_image_url|dimensions|dimension_text|size_text|spec|size|opening|opening_size|opening_mm|cutout|cutout_size|cutout_mm|cut_out|cut_out_size|hole|hole_size|hole_diameter|aperture|aperture_size|aperture_mm|cut_size|diameter|diameter_mm|outer_d|outer_diameter|outer_dia|outer_d_mm|dia|length|length_mm|len|width|width_mm|height|height_mm|updated_at|modified_at|created_at)$/i';
        $select=array(); foreach($cols as $c){ if(preg_match($allow,$c)) $select[]='`'.$c.'`'; }
        if(!$select) $select=array_map(function($c){return '`'.$c.'`';}, array_slice($cols,0,40));
        $order = in_array('updated_at',$cols,true)?'updated_at DESC,':'';
        $sql='SELECT '.implode(',',$select).' FROM `'.$t.'` WHERE `'.$modelCols[0]."`<>'' ORDER BY ".$order.'`'.$modelCols[0].'` ASC LIMIT 20000';
        $rows=ws_query_all($db,$sql);
        foreach($rows as $r){
            $model=ws_model_clean(ws_pick($r,$modelNames)); if($model==='') continue;
            $slug=ws_pick($r,array('slug','product_slug','url_slug'));
            $url=ws_pick($r,array('url','link','product_url')); if($url===''){ $url=$slug!==''?'/product.php?slug='.$slug:''; }
            $out[]=array(
                'source_id'=>ws_pick($r,array('size_id','variant_id','sku_id','id','product_id','slug')) ?: $model,
                'model_no'=>$model,
                'series_name'=>ws_pick($r,array('series_name','series','product_name','title','name','category_name')),
                'lamp_type'=>ws_pick($r,array('lamp_type','type_name','product_type','category','category_name')),
                'category'=>ws_pick($r,array('category','category_name','product_type','type_name')),
                'source_url'=>ws_abs($url),
                'web_image_url'=>ws_abs(ws_pick($r,array('image_path','image_url','main_image','cover_image','cover_url','image'))),
                'web_dimension_url'=>ws_abs(ws_pick($r,array('drawing_path','drawing_url','dimension_url','dimension_image','size_image_url','size_image','drawing'))),
                'web_dimensions'=>ws_pick($r,array('dimensions','dimension','dimension_text','size_text','size','spec')),
                'opening'=>ws_pick($r,array('opening','opening_size','opening_mm','cutout','cutout_size','cutout_mm','cut_out','cut_out_size','hole','hole_size','hole_diameter','aperture','aperture_size','aperture_mm','cut_size')),
                'diameter'=>ws_pick($r,array('diameter','diameter_mm','outer_d','outer_diameter','outer_dia','outer_d_mm','dia')),
                'length'=>ws_pick($r,array('length','length_mm','len')),
                'width'=>ws_pick($r,array('width','width_mm')),
                'height'=>ws_pick($r,array('height','height_mm')),
                'slug'=>$slug,
                'updated_at'=>ws_pick($r,array('updated_at','modified_at','created_at')),
                '_table'=>$t
            );
        }
        if($out) break;
    }
    return $out;
}
try {
    $token = ws_cfg_const_env(array('WEBSITE_NAMING_SYNC_TOKEN','NM_WEBSITE_SYNC_TOKEN','ARTDON_WEBSITE_SYNC_TOKEN'), '');
    if($token !== '' && ws_s($_GET['token'] ?? '') !== $token){ http_response_code(403); ws_json(null,false,'同步 token 不正确'); }
    $db=ws_db();
    $rows=ws_export_rows($db);
    ws_json(array('rows'=>$rows,'count'=>count($rows),'time'=>date('Y-m-d H:i:s'),'source'=>'website_naming_sync_api.php V1.3','db'=>ws_current_db_name($db)), true, '官网同步接口正常');
} catch(Throwable $e) {
    http_response_code(500);
    ws_json(null,false,$e->getMessage());
}
