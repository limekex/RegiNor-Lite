<?php
use RegiNor\Lite\Infrastructure\{CourseCalendar,CourseRepository,WebIdentity};
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]);
$id=(int)($args[1]??0);$repo=new CourseRepository();$g=$repo->get($id,'group');$period=$g['data']['period_id'];
if(get_post_field('post_title',$period)!=='HTTP offentlig periode'){throw new RuntimeException('Synthetic only');}
switch($args[0]??''){
case 'read':$c=CourseCalendar::read($id,'default');WP_CLI::line(wp_json_encode(['feed'=>CourseCalendar::url($c['identity'],'default'),'events'=>$c['events']]));break;
case 'move':
$repo->lifecycle($period,$repo->get($period)['version'],array_map(static fn($s)=>$s['version'],$repo->groups($period)),'draft');
$g=$repo->get($id);$s=$g['data']['sessions'][0];$repo->confirm($repo->previewSessionChange($id,$g['version'],$s['id'],['start_time'=>'18:30','end_time'=>'20:00','status'=>'moved','reason'=>'Flyttet en halvtime.']));
$repo->publish($repo->previewPublication($period,$repo->get($period)['version'],true));break;
case 'rename':$e=WebIdentity::entry($id);$e['slug']='http-calendar-renamed-'.$id;WebIdentity::save($id,WebIdentity::read($id)['version'],'default',$e);break;
default:throw new RuntimeException('Unknown operation');
}
