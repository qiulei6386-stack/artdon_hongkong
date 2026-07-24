<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once __DIR__ . '/_layout.php';
$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}web_migrate($pdo);$user=web_require_admin($pdo);
$site=web_get_block('site');$seo=web_get_block('seo');
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!web_verify_csrf($_POST['csrf']??null)){$_SESSION['admin_error']='页面已过期，请刷新后重试。';header('Location: settings.php');exit;}
    try{
        $headerLogo=trim((string)($_POST['header_logo']??''));$footerLogo=trim((string)($_POST['footer_logo']??''));
        if(!empty($_FILES['header_logo_upload'])&&($_FILES['header_logo_upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$headerLogo=web_upload_file($_FILES['header_logo_upload'],'image',$pdo,(int)$user['id'],'页头 LOGO','Artdon Lighting','images');}
        if(!empty($_FILES['footer_logo_upload'])&&($_FILES['footer_logo_upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$footerLogo=web_upload_file($_FILES['footer_logo_upload'],'image',$pdo,(int)$user['id'],'页脚 LOGO','Artdon Lighting','images');}
        $nav=[];$currentMenu=-1;foreach(preg_split('/\R+/',trim((string)($_POST['nav_text']??'')))?:[] as $line){$parts=array_map('trim',explode('|',$line,3));$kind=strtoupper($parts[0]??'');if($kind==='MENU'&&($parts[1]??'')!==''&&($parts[2]??'')!==''){$nav[]=['label'=>$parts[1],'href'=>$parts[2],'items'=>[]];$currentMenu=count($nav)-1;}elseif($kind==='ITEM'&&$currentMenu>=0&&($parts[1]??'')!==''&&($parts[2]??'')!==''){$nav[$currentMenu]['items'][]=['label'=>$parts[1],'href'=>$parts[2]];}}
        if(!$nav)$nav=$site['nav']??[];
        $site=[
            'company'=>trim((string)($_POST['company']??'')),'site_url'=>rtrim(trim((string)($_POST['site_url']??'')),'/'),'header_logo'=>$headerLogo,'footer_logo'=>$footerLogo,'tagline'=>trim((string)($_POST['tagline']??'')),
            'contact_name'=>trim((string)($_POST['contact_name']??'')),'email'=>trim((string)($_POST['email']??'')),'telephone'=>trim((string)($_POST['telephone']??'')),'mobile'=>trim((string)($_POST['mobile']??'')),'whatsapp'=>preg_replace('/\D+/','',(string)($_POST['whatsapp']??'')),'location'=>trim((string)($_POST['location']??'')),
            'facebook'=>trim((string)($_POST['facebook']??'')),'instagram'=>trim((string)($_POST['instagram']??'')),'linkedin'=>trim((string)($_POST['linkedin']??'')),'pinterest'=>trim((string)($_POST['pinterest']??'')),'youtube'=>trim((string)($_POST['youtube']??'')),'x'=>trim((string)($_POST['x']??'')),'tiktok'=>trim((string)($_POST['tiktok']??'')),'header_quote_label'=>trim((string)($_POST['header_quote_label']??'Get a Quote')),'header_quote_url'=>trim((string)($_POST['header_quote_url']??'index.php#contact')),'nav_schema_version'=>49,'nav'=>$nav,
        ];
        $og=trim((string)($_POST['og_image']??''));if(!empty($_FILES['og_image_upload'])&&($_FILES['og_image_upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$og=web_upload_file($_FILES['og_image_upload'],'image',$pdo,(int)$user['id'],'首页社交分享图','Artdon Lighting homepage','banners');}
        $seo=['title'=>trim((string)($_POST['seo_title']??'')),'description'=>trim((string)($_POST['seo_description']??'')),'og_image'=>$og,'robots'=>trim((string)($_POST['robots']??'index,follow,max-image-preview:large'))];
        web_save_block($pdo,'site',$site,(int)$user['id']);web_save_block($pdo,'seo',$seo,(int)$user['id']);web_log($pdo,(int)$user['id'],'update_site_settings','site_settings','site');
        $_SESSION['admin_success']='网站设置已保存。';header('Location: settings.php');exit;
    }catch(Throwable $e){$_SESSION['admin_error']='保存失败：'.$e->getMessage();header('Location: settings.php');exit;}
}
admin_page_start('网站设置','settings',$user);admin_notice();
?>
<form class="admin-card" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
<h2>品牌、联系方式与全站导航</h2><p>这里控制官网页头、页脚、联系方式、导航菜单和全局 SEO。官网当前使用 IP 地址，网站地址应填写 <code>http://43.132.210.162</code>。</p>
<div class="admin-form-grid">
<div class="field"><label>公司名称</label><input name="company" value="<?= web_e($site['company']??'') ?>"></div><div class="field"><label>网站地址</label><input name="site_url" value="<?= web_e($site['site_url']??'http://43.132.210.162') ?>"><span class="help">当前正确地址：http://43.132.210.162</span></div>
<div class="field"><label>页头 LOGO 路径</label><input name="header_logo" value="<?= web_e($site['header_logo']??'') ?>"><input type="file" name="header_logo_upload" accept="image/*"><?php if(!empty($site['header_logo'])):?><img class="preview-thumb" src="../<?= web_e($site['header_logo']) ?>" alt="页头 LOGO"><?php endif;?></div>
<div class="field"><label>默认页脚 LOGO 路径</label><input name="footer_logo" value="<?= web_e($site['footer_logo']??'') ?>"><input type="file" name="footer_logo_upload" accept="image/*"><?php if(!empty($site['footer_logo'])):?><img class="preview-thumb" src="../<?= web_e($site['footer_logo']) ?>" alt="页脚 LOGO"><?php endif;?></div>
<div class="field full"><label>品牌标语</label><input name="tagline" value="<?= web_e($site['tagline']??'') ?>"></div>
<div class="field"><label>联系人</label><input name="contact_name" value="<?= web_e($site['contact_name']??'') ?>"></div><div class="field"><label>邮箱</label><input name="email" value="<?= web_e($site['email']??'') ?>"></div>
<div class="field"><label>电话</label><input name="telephone" value="<?= web_e($site['telephone']??'') ?>"></div><div class="field"><label>手机</label><input name="mobile" value="<?= web_e($site['mobile']??'') ?>"></div>
<div class="field"><label>WhatsApp 数字号码</label><input name="whatsapp" value="<?= web_e($site['whatsapp']??'') ?>"></div><div class="field"><label>公司所在地</label><input name="location" value="<?= web_e($site['location']??'') ?>"></div>
<div class="field"><label>Facebook 地址</label><input name="facebook" value="<?= web_e($site['facebook']??'') ?>" placeholder="https://facebook.com/..."></div><div class="field"><label>Instagram 地址</label><input name="instagram" value="<?= web_e($site['instagram']??'') ?>"></div>
<div class="field"><label>LinkedIn 地址</label><input name="linkedin" value="<?= web_e($site['linkedin']??'') ?>"></div><div class="field"><label>YouTube 地址</label><input name="youtube" value="<?= web_e($site['youtube']??'') ?>"></div>
<div class="field"><label>Pinterest 地址</label><input name="pinterest" value="<?= web_e($site['pinterest']??'') ?>"></div><div class="field"><label>X / Twitter 地址</label><input name="x" value="<?= web_e($site['x']??'') ?>"></div>
<div class="field"><label>TikTok 地址</label><input name="tiktok" value="<?= web_e($site['tiktok']??'') ?>"></div><div class="field"><label>WhatsApp 链接</label><input value="https://wa.me/<?= web_e($site['whatsapp']??'') ?>" readonly><span class="help">WhatsApp 链接根据上面的号码自动生成。</span></div>
<div class="field full"><label>导航结构</label><textarea name="nav_text" style="min-height:320px"><?php foreach(($site['nav']??[]) as $n){ echo web_e('MENU|'.($n['label']??'').'|'.($n['href']??''))."\n"; foreach(($n['items']??[]) as $item){ echo web_e('ITEM|'.($item['label']??($item[0]??'')).'|'.($item['href']??($item[1]??'')))."\n"; } } ?></textarea><span class="help">顶部菜单格式：MENU|名称|地址；下拉项目格式：ITEM|名称|地址。</span></div>
<div class="field"><label>右侧询价按钮文字</label><input name="header_quote_label" value="<?= web_e($site['header_quote_label']??'Get a Quote') ?>"></div><div class="field"><label>右侧询价按钮地址</label><input name="header_quote_url" value="<?= web_e($site['header_quote_url']??'index.php#contact') ?>"><span class="help">首页会直接打开询价弹窗，其他页面按此地址跳转。</span></div>
</div>
<hr style="border:0;border-top:1px solid var(--line);margin:30px 0">
<h2>首页 SEO</h2><div class="admin-form-grid"><div class="field full"><label>SEO 标题</label><input name="seo_title" value="<?= web_e($seo['title']??'') ?>"></div><div class="field full"><label>Meta 描述</label><textarea name="seo_description"><?= web_e($seo['description']??'') ?></textarea></div><div class="field"><label>社交分享图片路径</label><input name="og_image" value="<?= web_e($seo['og_image']??'') ?>"><input type="file" name="og_image_upload" accept="image/*"></div><div class="field"><label>Robots 指令</label><input name="robots" value="<?= web_e($seo['robots']??'') ?>"></div></div>
<div class="admin-card" style="margin-top:28px;background:#f7f7f8">
<h2 style="margin-top:0">页脚已独立管理</h2>
<p>页脚栏目、链接、联系方式、订阅、政策与社交入口已经迁移到独立页面，避免保存网站设置时误覆盖页脚结构。</p>
<a class="admin-button-secondary" href="footer.php">打开页脚管理 →</a>
</div>
<div style="margin-top:22px"><button class="admin-button" type="submit">保存网站设置</button></div>
</form>
<?php admin_page_end(); ?>
