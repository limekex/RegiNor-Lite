<?php
use RegiNor\Lite\Infrastructure\{WebIdentity,CourseRepository,ContentTypes};
use RegiNor\Lite\Frontend\{PublicRoutes,PublicSite};
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local tests only'); }
wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]);
if (($args[0]??'')==='setup') {
    $f=json_decode(base64_decode($args[1]),true,32,JSON_THROW_ON_ERROR);
    if(get_post_field('post_title',$f['period'])!=='HTTP offentlig periode')throw new RuntimeException('Synthetic only');
    $old=['permalink_structure'=>get_option('permalink_structure',null),'rnl_pretty_urls'=>get_option('rnl_pretty_urls',null)];
    update_option('permalink_structure','/%postname%/');update_option('rnl_pretty_urls',true);PublicRoutes::rules();flush_rewrite_rules(false);
    $oldUrl=PublicSite::url($f['group']);$oldPeriod=PublicSite::url(null,['rnl_period'=>$f['period']]);
    $e=WebIdentity::entry($f['group']);$e['slug']='http-salsa-norsk-'.$f['group'];$e['title']='Salsa "Øvet" & moro';$e['description']='Delingsbeskrivelse uten HTML.';WebIdentity::save($f['group'],WebIdentity::read($f['group'])['version'],'default',$e);
    $middleUrl=PublicSite::url($f['group']);
    $e=WebIdentity::entry($f['period']);$e['slug']='http-host-'.$f['period'];WebIdentity::save($f['period'],WebIdentity::read($f['period'])['version'],'default',$e);
    WP_CLI::line(wp_json_encode(['old'=>$old,'old_url'=>$oldUrl,'middle_url'=>$middleUrl,'old_period'=>$oldPeriod,'course_url'=>PublicSite::url($f['group']),'period_url'=>PublicSite::url(null,['rnl_period'=>$f['period']]),'base'=>get_permalink($f['page'])]));return;
}
if(($args[0]??'')==='restore'){
 $old=json_decode(base64_decode($args[1]),true,32,JSON_THROW_ON_ERROR);foreach($old as$key=>$v){if($v===null)delete_option($key);else update_option($key,$v);}flush_rewrite_rules(false);return;
}
throw new RuntimeException('Unknown operation');
