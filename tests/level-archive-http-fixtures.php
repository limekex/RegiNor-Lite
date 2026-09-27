<?php
use RegiNor\Lite\Infrastructure\{CourseRepository,ContentTypes,LevelArchiveStore as Store};
use RegiNor\Lite\Frontend\{LevelArchive,PublicRoutes};
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]);
$command=$args[0]??'';$id=(int)($args[1]??0);$repo=new CourseRepository();
if($command==='setup'){
 if(get_post_type($id)!=='rnl_group'||get_post_field('post_title',$repo->get($id)['data']['period_id'])!=='HTTP offentlig periode')throw new RuntimeException('Synthetic only');
 $level=$repo->create('level',['title'=>'HTTP nivåarkiv','description'=>'Nivåforklaring','sort_order'=>1,'active'=>true]);
 $state=get_post_meta($id,ContentTypes::META,true);$state['data']['level_id']=$level;update_post_meta($id,ContentTypes::META,wp_slash($state));
 $term=wp_insert_term('HTTP arkiv '.wp_generate_uuid4(),'post_tag')['term_id'];$posts=[];
 foreach(['HTTP artikkel A','HTTP artikkel B','HTTP artikkel C','HEMMELIG ARTIKKEL']as$i=>$title){$post=$posts[]=wp_insert_post(['post_type'=>'post','post_status'=>$i===3?'private':'publish','post_title'=>$title,'post_content'=>'Et kort utdrag med trygg informasjon.']);wp_set_object_terms($post,[$term],'post_tag');}
 $input=['enabled'=>'1','slug'=>'http-niva-'.$level,'title'=>'HTTP nybegynnerarkiv','intro'=>'<p>INTRO ARKIV <strong>Velkommen</strong></p>','extra'=>'<p>EKSTRA ARKIV <a href="https://example.org/">Les mer</a></p>','description'=>'Nivåarkivets metadata','faq'=>'post:post_tag:'.$term,'articles'=>'post:post_tag:'.$term,'limit'=>'6','order'=>'title'];
 Store::save($level,0,'default',$input);PublicRoutes::rules();flush_rewrite_rules(false);
 WP_CLI::line(wp_json_encode(['id'=>$level,'posts'=>$posts,'term'=>$term,'url'=>LevelArchive::url($level)]));return;
}
if($command==='cleanup'){
 $data=json_decode(base64_decode($args[1]),true);foreach($data['posts']as$post)wp_delete_post($post,true);wp_delete_post($data['id'],true);wp_delete_term($data['term'],'post_tag');return;
}
if(get_post_type($id)!=='rnl_level'||get_post_field('post_title',$id)!=='HTTP nivåarkiv')throw new RuntimeException('Synthetic only');
$state=Store::read($id);$input=Store::entry($id,'default')+$state;
if($command==='hide')$input['enabled']='0';elseif($command==='rename')$input['slug']='http-renamed-'.$id;else throw new RuntimeException('Unknown action');
Store::save($id,$state['version'],'default',$input);WP_CLI::line(wp_json_encode(['url'=>LevelArchive::url($id)]));
