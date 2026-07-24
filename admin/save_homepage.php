<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}web_migrate($pdo);$user=web_require_admin($pdo);
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: homepage.php');exit;}
if(!web_verify_csrf($_POST['csrf']??null)){$_SESSION['admin_error']='页面已过期，请刷新后重试。';header('Location: homepage.php');exit;}
$section=(string)($_POST['section']??'');
$allowed=['layout','hero','why','reasons','products','featured_system','projects','solutions','downloads','insights','inquiry'];
if(!in_array($section,$allowed,true)){$_SESSION['admin_error']='未知的首页版块。';header('Location: homepage.php');exit;}

function clean_text(mixed $value): string{return trim((string)$value);}
function clean_rows(mixed $rows): array{return is_array($rows)?array_filter($rows,'is_array'):[];}
function clean_pairs(string $text,string $keyName,string $labelName): array{
    $out=[];
    foreach(preg_split('/\R+/',trim($text))?:[] as $line){
        $line=trim($line);if($line==='')continue;
        [$a,$b]=array_pad(array_map('trim',explode('|',$line,2)),2,'');
        if($a!==''&&$b!=='')$out[]=[ $keyName=>$a, $labelName=>$b ];
    }
    return $out;
}
function maybe_upload(string $field,string $kind,PDO $pdo,int $userId,string $title='',string $alt='',string $usage=''): string{
    if(empty($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
    return web_upload_file($_FILES[$field],$kind,$pdo,$userId,$title,$alt,$usage);
}

try{
    $current=web_get_block($section);
    switch($section){
        case 'layout':
            $known = [
                'hero'=>'首页轮播','why'=>'关于我们','reasons'=>'合作优势','products'=>'首页产品','featured_system'=>'重点系统',
                'projects'=>'项目案例','solutions'=>'应用方案','downloads'=>'下载中心','insights'=>'知识文章','inquiry'=>'询盘表单',
            ];
            $sections=[];$seen=[];$order=10;
            foreach(clean_rows($_POST['sections']??[]) as $row){
                $key=clean_text($row['key']??'');
                if(!isset($known[$key])||isset($seen[$key]))continue;
                $seen[$key]=true;
                $theme=$key==='hero'?'light':(in_array(($row['theme']??''),['light','dark'],true)?(string)$row['theme']:($key==='solutions'?'dark':'light'));
                $sections[]=['key'=>$key,'label'=>$known[$key],'active'=>!empty($row['active'])?1:0,'order'=>$order,'theme'=>$theme];
                $order+=10;
            }
            foreach($known as $key=>$label){
                if(isset($seen[$key]))continue;
                $sections[]=['key'=>$key,'label'=>$label,'active'=>1,'order'=>$order,'theme'=>$key==='solutions'?'dark':'light'];
                $order+=10;
            }
            $data=['sections'=>$sections];
            break;
        case 'hero':
            $slides=[];
            foreach(clean_rows($_POST['slides']??[]) as $i=>$row){
                $image=clean_text($row['image']??'');
                $video=clean_text($row['video']??'');
                $uploaded=maybe_upload('hero_image_upload_'.$i,'image',$pdo,(int)$user['id'],clean_text($row['title']??''),clean_text($row['alt']??''),'banners');
                if($uploaded!=='')$image=$uploaded;
                $uploadedVideo=maybe_upload('hero_video_upload_'.$i,'video',$pdo,(int)$user['id'],clean_text($row['title']??''),'','videos');
                if($uploadedVideo!=='')$video=$uploadedVideo;
                if(clean_text($row['title']??'')==='')continue;
                $slides[]=[
                    'active'=>!empty($row['active'])?1:0,
                    'eyebrow'=>clean_text($row['eyebrow']??''),'title'=>clean_text($row['title']??''),'desc'=>clean_text($row['desc']??''),
                    'image'=>$image,'video'=>$video,'alt'=>clean_text($row['alt']??''),'link'=>clean_text($row['link']??''),'cta'=>clean_text($row['cta']??''),'quote_product'=>clean_text($row['quote_product']??''),
                ];
            }
            $data=['quote_button'=>clean_text($_POST['quote_button']??'Get a quote'),'slides'=>$slides];
            break;
        case 'why':
            $cards=[];foreach(clean_rows($_POST['cards']??[]) as $row){if(clean_text($row['title']??'')==='')continue;$cards[]=['active'=>!empty($row['active'])?1:0,'icon'=>clean_text($row['icon']??'factory'),'title'=>clean_text($row['title']??''),'text'=>clean_text($row['text']??'')];}
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'intro'=>clean_text($_POST['intro']??''),'cards'=>$cards];
            break;

        case 'reasons':
            $cards=[];
            foreach(clean_rows($_POST['cards']??[]) as $row){
                if(clean_text($row['title']??'')==='')continue;
                $cards[]=[
                    'active'=>!empty($row['active'])?1:0,
                    'icon'=>clean_text($row['icon']??'experience'),
                    'badge'=>clean_text($row['badge']??''),
                    'title'=>clean_text($row['title']??''),
                    'text'=>clean_text($row['text']??''),
                    'button_label'=>clean_text($row['button_label']??''),
                    'button_url'=>clean_text($row['button_url']??''),
                ];
            }
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'intro'=>clean_text($_POST['intro']??''),'cards'=>$cards];
            break;
        case 'products':
            // V6.12: the homepage product cards are managed by the dedicated
            // real-product publishing centre. Preserve legacy cards/tabs as a
            // safe fallback and update only the section heading/link here.
            $data=$current;
            $data['eyebrow']=clean_text($_POST['eyebrow']??($current['eyebrow']??''));
            $data['title']=clean_text($_POST['title']??($current['title']??''));
            $data['view_all_label']=clean_text($_POST['view_all_label']??($current['view_all_label']??''));
            $data['view_all_url']=clean_text($_POST['view_all_url']??($current['view_all_url']??'products.php'));
            break;
        case 'featured_system':
            $image=clean_text($_POST['image']??'');$uploaded=maybe_upload('featured_system_image_upload','image',$pdo,(int)$user['id'],clean_text($_POST['title']??''),clean_text($_POST['alt']??''),'products');if($uploaded!=='')$image=$uploaded;
            $features=[];foreach(clean_rows($_POST['features']??[]) as $row){if(clean_text($row['title']??'')==='')continue;$features[]=['title'=>clean_text($row['title']??''),'text'=>clean_text($row['text']??'')];}
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'text'=>clean_text($_POST['text']??''),'image'=>$image,'alt'=>clean_text($_POST['alt']??''),'url'=>clean_text($_POST['url']??''),'link_label'=>clean_text($_POST['link_label']??''),'features'=>$features];
            break;
        case 'projects':
            $items=[];foreach(clean_rows($_POST['items']??[]) as $i=>$row){$title=clean_text($row['title']??'');if($title==='')continue;$image=clean_text($row['image']??'');$uploaded=maybe_upload('project_image_upload_'.$i,'image',$pdo,(int)$user['id'],$title,$title,'projects');if($uploaded!=='')$image=$uploaded;$items[]=['active'=>!empty($row['active'])?1:0,'type'=>clean_text($row['type']??''),'year'=>clean_text($row['year']??''),'title'=>$title,'place'=>clean_text($row['place']??''),'desc'=>clean_text($row['desc']??''),'image'=>$image,'url'=>clean_text($row['url']??'')];}
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'intro'=>clean_text($_POST['intro']??''),'view_all_label'=>clean_text($_POST['view_all_label']??''),'view_all_url'=>clean_text($_POST['view_all_url']??''),'items'=>$items];
            break;

        case 'solutions':
            $items=[];
            foreach(clean_rows($_POST['items']??[]) as $i=>$row){
                $title=clean_text($row['title']??''); if($title==='')continue;
                $image=clean_text($row['image']??'');
                $uploaded=maybe_upload('solution_image_upload_'.$i,'image',$pdo,(int)$user['id'],$title,clean_text($row['alt']??''),'projects');
                if($uploaded!=='')$image=$uploaded;
                $icon=clean_text($row['icon']??'retail'); if(!web_solution_icon_key_exists($pdo,$icon))$icon='retail';
                $items[]=['active'=>!empty($row['active'])?1:0,'icon'=>$icon,'tag'=>clean_text($row['tag']??''),'title'=>$title,'text'=>clean_text($row['text']??''),'image'=>$image,'alt'=>clean_text($row['alt']??''),'url'=>clean_text($row['url']??'')];
            }
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'intro'=>clean_text($_POST['intro']??''),'items'=>$items];
            break;
        case 'downloads':
            $items=[];foreach(clean_rows($_POST['items']??[]) as $row){if(clean_text($row['title']??'')==='')continue;$items[]=['active'=>!empty($row['active'])?1:0,'title'=>clean_text($row['title']??''),'desc'=>clean_text($row['desc']??''),'type'=>clean_text($row['type']??'')];}
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'intro'=>clean_text($_POST['intro']??''),'search_placeholder'=>clean_text($_POST['search_placeholder']??''),'search_button'=>clean_text($_POST['search_button']??''),'open_label'=>clean_text($_POST['open_label']??'Open'),'items'=>$items];
            break;
        case 'insights':
            $items=[];foreach(clean_rows($_POST['items']??[]) as $i=>$row){$title=clean_text($row['title']??'');if($title==='')continue;$image=clean_text($row['image']??'');$uploaded=maybe_upload('insight_image_upload_'.$i,'image',$pdo,(int)$user['id'],$title,clean_text($row['alt']??''),'articles');if($uploaded!=='')$image=$uploaded;$items[]=['active'=>!empty($row['active'])?1:0,'tag'=>clean_text($row['tag']??''),'title'=>$title,'text'=>clean_text($row['text']??''),'image'=>$image,'alt'=>clean_text($row['alt']??''),'date'=>clean_text($row['date']??''),'read'=>clean_text($row['read']??''),'url'=>clean_text($row['url']??''),'fit'=>in_array(($row['fit']??'cover'),['cover','contain'],true)?$row['fit']:'cover'];}
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'intro'=>clean_text($_POST['intro']??''),'view_all_label'=>clean_text($_POST['view_all_label']??''),'view_all_url'=>clean_text($_POST['view_all_url']??''),'items'=>$items];
            break;
        default:
            $options=clean_pairs((string)($_POST['support_options_text']??''),'value','label');if(!$options)$options=$current['support_options']??[];
            $data=['eyebrow'=>clean_text($_POST['eyebrow']??''),'title'=>clean_text($_POST['title']??''),'intro'=>clean_text($_POST['intro']??''),'button'=>clean_text($_POST['button']??''),'success_message'=>clean_text($_POST['success_message']??''),'quote_eyebrow'=>clean_text($_POST['quote_eyebrow']??''),'quote_title'=>clean_text($_POST['quote_title']??''),'quote_intro'=>clean_text($_POST['quote_intro']??''),'quote_button'=>clean_text($_POST['quote_button']??''),'quote_note'=>clean_text($_POST['quote_note']??''),'name_label'=>clean_text($_POST['name_label']??''),'name_placeholder'=>clean_text($_POST['name_placeholder']??''),'email_label'=>clean_text($_POST['email_label']??''),'email_placeholder'=>clean_text($_POST['email_placeholder']??''),'company_label'=>clean_text($_POST['company_label']??''),'company_placeholder'=>clean_text($_POST['company_placeholder']??''),'country_label'=>clean_text($_POST['country_label']??''),'country_placeholder'=>clean_text($_POST['country_placeholder']??''),'support_label'=>clean_text($_POST['support_label']??''),'message_label'=>clean_text($_POST['message_label']??''),'message_placeholder'=>clean_text($_POST['message_placeholder']??''),'consent_text'=>clean_text($_POST['consent_text']??''),'response_note'=>clean_text($_POST['response_note']??''),'quote_name_label'=>clean_text($_POST['quote_name_label']??''),'quote_email_label'=>clean_text($_POST['quote_email_label']??''),'quote_product_label'=>clean_text($_POST['quote_product_label']??''),'support_options'=>$options];
    }
    $blockKey=$section==='layout'?'homepage_layout':$section;
    web_save_block($pdo,$blockKey,$data,(int)$user['id']);web_log($pdo,(int)$user['id'],'update_content','homepage_section',$blockKey,['item_count'=>count($data['items']??$data['slides']??$data['cards']??$data['sections']??[])]);
    $_SESSION['admin_success']=$section==='layout'?'首页版块顺序、显示状态和底色已发布。':'首页版块已保存并发布。';
}catch(Throwable $e){$_SESSION['admin_error']='保存失败：'.$e->getMessage();}
header('Location: homepage.php?section='.rawurlencode($section));
