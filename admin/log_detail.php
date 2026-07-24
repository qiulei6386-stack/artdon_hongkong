<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once __DIR__ . '/_layout.php';
$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}web_migrate($pdo);$user=web_require_admin($pdo);web_admin_security_migrate($pdo);
$source=(string)($_GET['source']??'audit');$id=max(1,(int)($_GET['id']??0));$row=null;$back='actions';
if($source==='audit'){$stmt=$pdo->prepare("SELECT l.*,COALESCE(u.display_name,u.username,l.username_snapshot,'系统') actor FROM web_audit_logs l LEFT JOIN web_admin_users u ON u.id=l.user_id WHERE l.id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
elseif($source==='legacy'){$stmt=$pdo->prepare("SELECT l.*,COALESCE(u.display_name,u.username,'系统') actor FROM web_admin_logs l LEFT JOIN web_admin_users u ON u.id=l.user_id WHERE l.id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
elseif($source==='login'){$back='security';$stmt=$pdo->prepare("SELECT a.*,COALESCE(u.display_name,u.username,a.username) actor FROM web_admin_login_attempts a LEFT JOIN web_admin_users u ON u.id=a.user_id WHERE a.id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
elseif($source==='sync'){$back='sync';$stmt=$pdo->prepare('SELECT * FROM web_sync_logs WHERE id=?');$stmt->execute([$id]);$row=$stmt->fetch();}
elseif($source==='session'){$back='sessions';$stmt=$pdo->prepare("SELECT s.*,COALESCE(u.display_name,u.username,'未知账号') actor,u.username FROM web_admin_sessions s LEFT JOIN web_admin_users u ON u.id=s.user_id WHERE s.id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
if(!$row){http_response_code(404);$_SESSION['admin_error']='日志记录不存在或已被清理。';header('Location: logs.php?tab='.$back);exit;}

function log_detail_result_label(string $value): string
{
    return [
        'success'=>'成功','failure'=>'失败','error'=>'错误','denied'=>'已拒绝','locked'=>'已锁定','blocked'=>'已阻止',
        'active'=>'有效','revoked'=>'已撤销','expired'=>'已过期','ignored'=>'已忽略','warning'=>'警告',
    ][$value] ?? $value;
}

function log_detail_pretty(mixed $json):string{if($json===null||$json==='')return '';if(is_string($json)){$decoded=json_decode($json,true);if(json_last_error()===JSON_ERROR_NONE)$json=$decoded;}return json_encode(web_admin_sanitize($json),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)?:'';}
admin_page_start('日志详情','logs',$user);admin_notice();
?>
<div class="security-page log-detail-page"><div class="security-detail-top"><a class="admin-button-secondary" href="logs.php?tab=<?= web_e($back) ?>">← 返回日志中心</a><span class="status-badge is-muted"><?= web_e($source) ?> #<?= $id ?></span></div>
<section class="admin-card security-section-card"><div class="security-card-head"><div><span class="security-eyebrow">Log detail</span><h2><?php if($source==='audit'||$source==='legacy'):?><?= web_e(admin_action_label((string)$row['action'])) ?><?php elseif($source==='login'):?>登录<?= web_e(log_detail_result_label((string)$row['result'])) ?><?php elseif($source==='sync'):?><?= web_e(admin_event_label((string)$row['event_type'])) ?><?php else:?>登录会话<?php endif;?></h2></div></div>
<div class="log-detail-meta">
<?php if($source==='audit'):?>
<div><span>时间</span><strong><?= web_e($row['created_at']) ?></strong></div><div><span>操作人</span><strong><?= web_e($row['actor']) ?></strong></div><div><span>结果</span><strong><?= web_e(log_detail_result_label((string)$row['result'])) ?></strong></div><div><span>级别</span><strong><?= web_e($row['severity']) ?></strong></div><div><span>模块</span><strong><?= web_e($row['module']) ?></strong></div><div><span>对象</span><strong><?= web_e(trim($row['target_type'].' '.$row['target_key'])) ?></strong></div><div><span>请求方式</span><strong><?= web_e($row['method']) ?></strong></div><div><span>HTTP</span><strong><?= web_e($row['http_status']??'—') ?></strong></div><div><span>耗时</span><strong><?= web_e($row['duration_ms']!==null?$row['duration_ms'].' ms':'—') ?></strong></div><div><span>IP</span><strong><?= web_e($row['ip_address']) ?></strong></div><div class="span-2"><span>页面</span><strong><?= web_e($row['route']) ?></strong></div><div class="span-2"><span>说明</span><strong><?= web_e($row['summary']?:'—') ?></strong></div>
<?php elseif($source==='legacy'):?>
<div><span>时间</span><strong><?= web_e($row['created_at']) ?></strong></div><div><span>操作人</span><strong><?= web_e($row['actor']) ?></strong></div><div><span>操作</span><strong><?= web_e(admin_action_label((string)$row['action'])) ?></strong></div><div><span>对象</span><strong><?= web_e(trim($row['target_type'].' '.$row['target_key'])) ?></strong></div><div><span>IP</span><strong><?= web_e($row['ip_address']) ?></strong></div>
<?php elseif($source==='login'):?>
<div><span>时间</span><strong><?= web_e($row['created_at']) ?></strong></div><div><span>用户</span><strong><?= web_e($row['actor']) ?></strong></div><div><span>登录账号</span><strong><?= web_e($row['username']) ?></strong></div><div><span>结果</span><strong><?= web_e(log_detail_result_label((string)$row['result'])) ?></strong></div><div><span>原因</span><strong><?= web_e($row['reason']) ?></strong></div><div><span>IP</span><strong><?= web_e($row['ip_address']) ?></strong></div><div class="span-2"><span>设备</span><strong><?= web_e($row['user_agent']) ?></strong></div>
<?php elseif($source==='sync'):?>
<div><span>时间</span><strong><?= web_e($row['created_at']) ?></strong></div><div><span>方向</span><strong><?= web_e(admin_status_label((string)$row['direction'])) ?></strong></div><div><span>事件</span><strong><?= web_e(admin_event_label((string)$row['event_type'])) ?></strong></div><div><span>结果</span><strong><?= web_e(log_detail_result_label((string)$row['result'])) ?></strong></div><div><span>HTTP</span><strong><?= web_e($row['http_status']??'—') ?></strong></div><div class="span-2"><span>消息</span><strong><?= web_e($row['message']) ?></strong></div>
<?php else:$state=!empty($row['revoked_at'])?'revoked':(strtotime((string)$row['expires_at'])<=time()?'expired':'active');?>
<div><span>用户</span><strong><?= web_e($row['actor']) ?></strong></div><div><span>状态</span><strong><?= web_e(log_detail_result_label($state)) ?></strong></div><div><span>创建</span><strong><?= web_e($row['created_at']) ?></strong></div><div><span>最近活动</span><strong><?= web_e($row['last_seen_at']) ?></strong></div><div><span>到期</span><strong><?= web_e($row['expires_at']) ?></strong></div><div><span>撤销</span><strong><?= web_e($row['revoked_at']??'—') ?></strong></div><div><span>IP</span><strong><?= web_e($row['ip_address']) ?></strong></div><div class="span-2"><span>设备</span><strong><?= web_e($row['user_agent']) ?></strong></div>
<?php endif;?>
</div></section>
<?php if($source==='audit'&&log_detail_pretty($row['before_json'])!==''):?><section class="admin-card security-json-card"><h3>修改前</h3><pre><?= web_e(log_detail_pretty($row['before_json'])) ?></pre></section><?php endif;?>
<?php if($source==='audit'&&log_detail_pretty($row['after_json'])!==''):?><section class="admin-card security-json-card"><h3>修改后</h3><pre><?= web_e(log_detail_pretty($row['after_json'])) ?></pre></section><?php endif;?>
<?php $detail=$source==='audit'?($row['detail_json']??''):($source==='legacy'?($row['detail_json']??''):($source==='sync'?($row['detail_json']??''):''));if(log_detail_pretty($detail)!==''):?><section class="admin-card security-json-card"><h3>详细数据</h3><pre><?= web_e(log_detail_pretty($detail)) ?></pre></section><?php endif;?>
</div>
<?php admin_page_end(); ?>
