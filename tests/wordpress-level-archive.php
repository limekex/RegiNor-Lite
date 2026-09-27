<?php
use RegiNor\Lite\Infrastructure\{CourseRepository,LevelArchiveStore as Store,WebIdentity,ContentTypes};
use RegiNor\Lite\Frontend\{LevelArchive,PublicRoutes,PublicSite,SharingMetadata,CourseSitemap};
use RegiNor\Lite\Admin\{CourseActions,LevelArchiveSettings};
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local tests only'); }
$ids=[];$terms=[];$checks=0;$oldUser=get_current_user_id();$oldPage=get_option('rnl_course_page_id',null);$oldGet=$_GET;
$assert=static function($ok,$message)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($message);};
$repo=new CourseRepository();$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
$reject=static function($fn)use($assert){try{$fn();}catch(Throwable){$assert(true,'Rejected');return;}$assert(false,'Expected rejection');};
try{
wp_set_current_user($admin);
$page=$ids[]=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Level archive test','post_content'=>'[reginor_courses]']);update_option('rnl_course_page_id',$page);
$level=$ids[]=$repo->create('level',['title'=>'Nybegynner arkivtest','description'=>'Kort nivåforklaring','sort_order'=>1,'active'=>true]);
$second=$ids[]=$repo->create('level',['title'=>'Annet nivå arkivtest','description'=>'','sort_order'=>2,'active'=>true]);
$term=$terms[]=wp_insert_term('Nivåarkivtest '.wp_generate_uuid4(),'post_tag')['term_id'];
$translatedTerm=$terms[]=wp_insert_term('Translated '.wp_generate_uuid4(),'post_tag')['term_id'];
$make=static function($title,$status='publish',$password='')use(&$ids,$term){$id=$ids[]=wp_insert_post(['post_type'=>'post','post_status'=>$status,'post_title'=>$title,'post_content'=>'<p>Et kort og godt svar.</p>','post_password'=>$password]);wp_set_object_terms($id,[$term],'post_tag');return$id;};
$article=$make('AAA artikkel');$faq=$make('BBB spørsmål');$private=$make('PRIVAT INNHOLD','private');$draft=$make('KLADD INNHOLD','draft');$locked=$make('PASSORD INNHOLD','publish','secret');
$input=['enabled'=>'1','slug'=>'nivaatest-'.$level,'title'=>'Nybegynner','intro'=>'<p>Velkommen <strong>hit</strong></p><script>bad()</script>','extra'=>'<p>Mer info <a href="https://example.org/">her</a></p>','description'=>'Om nivået','faq'=>'post:post_tag:'.$term,'articles'=>'post:post_tag:'.$term,'limit'=>'6','order'=>'title'];
$assert(Store::publicData($level)===null,'New level archive exposed');
$save=static function($id,$input,$language='default') {Store::save($id,Store::read($id)['version'],$language,$input);};
$save($level,$input);$entry=Store::publicData($level);
$assert($entry['title']==='Nybegynner'&&!str_contains($entry['intro'],'script'),'Archive not saved/sanitized');
$assert(str_contains($entry['extra'],'target="_blank"'),'HTML link lost');
$assert(array_column(Store::related($entry['settings'],'faq'),'ID')===[$article,$faq],'Related visibility/order failed');
$assert(LevelArchive::resolve($entry['slug'])['id']===$level,'Route failed');
$assert(str_contains(LevelArchive::url($level),'/kursrekke/niva/'),'Wrong namespace');
$reject(fn()=>Store::save($level,0,'default',$input));
$reject(fn()=>$save($second,$input));
$reject(fn()=>$save($level,array_replace($input,['faq'=>'rnl_level:post_tag:'.$term])));
$reject(fn()=>$save($level,array_replace($input,['articles'=>'post:nonexistent:'.$term])));
$reject(fn()=>$save($level,array_replace($input,['limit'=>'1000'])));
$reject(fn()=>$save($level,array_replace($input,['slug'=>['bad']])));
$reject(fn()=>$save($level,$input,'zz'));
wp_set_current_user(0);$reject(fn()=>$save($level,$input));wp_set_current_user($admin);
$actions=new CourseActions();$reject(fn()=>$actions->handle(['command'=>'save_level_archive','id'=>$level,'version'=>1,'data'=>$input]));
$_GET=['page'=>'rnl-resources'];ob_start();LevelArchiveSettings::render($level,'Test');$form=ob_get_clean();
$assert(str_contains($form,'data-rnl-rich-text')&&str_contains($form,'Stikkord for spørsmål')&&str_contains($form,'data[enabled]'),'Missing editor/source/publish controls');
$_GET=[];$html=LevelArchive::render($entry);
$assert(str_contains($html,'Nye kurs er ikke publisert ennå')&&str_contains($html,'Velkommen')&&str_contains($html,'Mer info'),'Empty archive lost editorial text');
$assert(str_contains($html,'rnl-faq-list')&&str_contains($html,'Les hele svaret')&&str_contains($html,'data-rnl-carousel-play'),'FAQ preview/carousel missing');
$assert(!str_contains($html,'PRIVAT INNHOLD')&&!str_contains($html,'PASSORD INNHOLD')&&!str_contains($html,'KLADD INNHOLD'),'Private content leaked');
$oldSlug=$input['slug'];$input['slug']='endret-'.$level;$save($level,$input);
$assert(LevelArchive::resolve($oldSlug)['id']===$level,'Alias lost');$reject(fn()=>$save($second,array_replace($input,['slug'=>$oldSlug])));
// Simulate translated term and post mapping; missing translations must never become originals.
$english=$make('English article');wp_set_object_terms($english,[$translatedTerm],'post_tag');
$lang=static fn()=> 'en';$langs=static fn()=>['en'=>['native_name'=>'English','default_locale'=>'en_US']];
$mapping=static function($id,$type,$fallback=true,$language=null)use($page,$term,$translatedTerm,$article,$english){if($type==='page')return$page;if($type==='post_tag')return$id===$term?$translatedTerm:$id;if($type==='post')return$id===$article||$id===$english?$english:null;return$id;};
add_filter('wpml_current_language',$lang);add_filter('wpml_active_languages',$langs);add_filter('wpml_object_id',$mapping,10,4);
$save($level,array_replace($input,['slug'=>'beginner-'.$level,'title'=>'Beginner']),'en');
$assert(Store::publicData($level)['title']==='Beginner'&&Store::entry($level,'default')['title']==='Nybegynner','Language overwrote original');
$assert(array_column(Store::related(Store::read($level),'articles'),'ID')===[$english],'Wrong translated selection');
$assert(LevelArchive::resolve('beginner-'.$level,'en')['id']===$level,'Translated slug failed');
remove_filter('wpml_current_language',$lang);remove_filter('wpml_active_languages',$langs);remove_filter('wpml_object_id',$mapping,10);
$input['enabled']='0';$save($level,$input);$assert(Store::publicData($level)===null&&LevelArchive::resolve($oldSlug)===null,'Hidden alias exposed');
$assert(!in_array(LevelArchive::url($level),array_column((new CourseSitemap())->get_url_list(1),'loc'),true),'Hidden archive in sitemap');
// niva is reserved only for new period identities; no legacy taxonomy mutation.
$period=$ids[]=$repo->create('period',['title'=>'niva','timezone'=>'Europe/Oslo','start_date'=>'2030-01-07','default_session_count'=>1,'default_room_id'=>0,'default_price_minor'=>0,'default_price_basis'=>'person','visible_from'=>'2020-01-01T00:00:00Z','visible_until'=>'2031-01-01T00:00:00Z','sales_from'=>'2020-01-01T00:00:00Z','sales_until'=>'2031-01-01T00:00:00Z','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[]]);
$assert(WebIdentity::entry($period)['slug']!=='niva','Reserved period slug allowed');
register_post_type('archive_faq',['public'=>true,'label'=>'Test FAQs','rewrite'=>false]);register_taxonomy('archive_faq_tag','archive_faq',['public'=>true,'show_ui'=>true]);
$faqTerm=wp_insert_term('Question tag','archive_faq_tag')['term_id'];
$faqPost=$ids[]=wp_insert_post(['post_type'=>'archive_faq','post_status'=>'publish','post_title'=>'Question','post_content'=>'Answer']);wp_set_object_terms($faqPost,[$faqTerm],'archive_faq_tag');
$assert(array_column(Store::related(['faq'=>'archive_faq:archive_faq_tag:'.$faqTerm,'limit'=>3,'order'=>'title'],'faq'),'ID')===[$faqPost],'Custom FAQ type/taxonomy failed');
wp_delete_term($faqTerm,'archive_faq_tag');unregister_taxonomy('archive_faq_tag');unregister_post_type('archive_faq');
register_taxonomy('archive_collision','post',['public'=>true,'rewrite'=>['slug'=>'kursrekke/niva']]);
$assert(!PublicRoutes::enabled()&&!LevelArchive::prettyEnabled(),'Nested taxonomy collision ignored');
unregister_taxonomy('archive_collision');

$web=WebIdentity::entry($period);$web['slug']='niva';$reject(fn()=>WebIdentity::save($period,WebIdentity::read($period)['version'],'default',$web));
WP_CLI::success('Level archive checks passed: '.$checks);
}finally{
LevelArchive::$selection=null;$_GET=$oldGet;wp_set_current_user($admin);foreach(array_reverse($ids)as$id)wp_delete_post($id,true);foreach($terms as$term)wp_delete_term($term,'post_tag');if($oldPage===null)delete_option('rnl_course_page_id');else update_option('rnl_course_page_id',$oldPage);wp_set_current_user($oldUser);
}
