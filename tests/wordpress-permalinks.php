<?php
use RegiNor\Lite\Infrastructure\{CourseRepository,ContentTypes,WebIdentity};
use RegiNor\Lite\Frontend\{Catalog,PublicRoutes,PublicSite,SharingMetadata,SchemaPresenter,CourseSitemap};
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local tests only'); }
$created=[]; $checks=0; $original=get_current_user_id(); $oldQuery=$GLOBALS['wp_query']; $oldGet=$_GET;
$options=[]; foreach(['rnl_course_page_id','permalink_structure','rnl_pretty_urls','rnl_sharing_image','rnl_web_version','rnl_public_routes_version'] as $key){$options[$key]=get_option($key,null);}
$assert=static function($ok,$message)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($message);};
$denied=static function($fn)use($assert){try{$fn();}catch(Throwable){$assert(true,'rejected');return;}$assert(false,'Expected rejected mutation');};
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
$repo=new CourseRepository();
try {
wp_set_current_user($admin); update_option('permalink_structure','/%postname%/'); update_option('rnl_pretty_urls',true);
$page=$created[]=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'M41 tests','post_content'=>'[reginor_courses]']);update_option('rnl_course_page_id',$page);
$assert(PublicSite::url()===home_url('/kursrekke/'),'Overview URL does not use route root');
delete_option('rnl_public_routes_version');PublicRoutes::upgrade();
$assert(isset(get_option('rewrite_rules')['^kursrekke/?$']),'Root rule missing after upgrade');
$venue=$created[]=$repo->create('venue',['title'=>'Kartsted','address'=>'Testgata 12','latitude'=>59.91,'longitude'=>10.75]);
$room=$created[]=$repo->create('room',['title'=>'Sal A','venue_id'=>$venue]);
$course=$created[]=$repo->create('course',['title'=>'Salsa Øvet','description'=>'Lær å danse salsa sammen.','level_description'=>'Litt erfaring.','dance_style'=>'Salsa','partner_info'=>'Kom alene.']);
$p=['title'=>'Høst Oslo 2030','timezone'=>'Europe/Oslo','start_date'=>'2030-09-02','default_session_count'=>2,'default_room_id'=>$room,'default_price_minor'=>120000,'default_price_basis'=>'person','visible_from'=>'2020-01-01T00:00:00Z','visible_until'=>'2031-01-01T00:00:00Z','sales_from'=>'2020-01-01T00:00:00Z','sales_until'=>'2031-01-01T00:00:00Z','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[]];
$period=$created[]=$repo->create('period',$p);
$other=$created[]=$repo->create('period',$p);
$group=$created[]=$repo->createGroup($period,$course,['weekday'=>1,'start_time'=>'18:00','end_time'=>'19:00','registration_status'=>'available','registration_url'=>'https://www.letsreg.com/event/test']);
$duplicate=$created[]=$repo->createGroup($period,$course,['weekday'=>2,'start_time'=>'18:00','end_time'=>'19:00']);
// Simulate pre-M4.1 records and a file update without any administrator visit.
foreach ([$period,$other,$group,$duplicate] as $legacy) { delete_post_meta($legacy,WebIdentity::META); }
update_option('rnl_web_version','1');wp_set_current_user(0);WebIdentity::upgrade();
$assert(WebIdentity::entry($period)['slug']==='host-oslo-2030','Existing period did not get its name as slug');
$assert(WebIdentity::entry($group)['slug']==='salsa-ovet','Existing course did not get its name as slug');
$assert(in_array('kurs-'.$period,WebIdentity::entry($period)['aliases'],true),'Old ID-based period link lost');
$assert(in_array('kurs-'.$group,WebIdentity::entry($group)['aliases'],true),'Old ID-based course link lost');
$assert(get_option('rnl_web_version')==='2','Anonymous installation upgrade not completed');
$identity=WebIdentity::read($group);WebIdentity::upgrade();
$assert(WebIdentity::read($group)===$identity,'Repeated upgrade changed identity');
wp_set_current_user($admin);
$assert(WebIdentity::entry($period)['slug']!==WebIdentity::entry($other)['slug'],'Duplicate period slug');
$assert(WebIdentity::entry($group)['slug']!==WebIdentity::entry($duplicate)['slug'],'Duplicate course slug');
$initial=WebIdentity::entry($group)['slug']; $initialPeriod=WebIdentity::entry($period)['slug'];
$g=$repo->get($group);$repo->confirm($repo->previewGroup($group,$g['version'],['title'=>'Nytt navn']));
$assert(WebIdentity::entry($group)['slug']===$initial,'Title change changed URL');
$input=WebIdentity::entry($group);$input['slug']='salsa-ny-adresse';$input['title']='Salsa & dans';$input['description']='Trygg <b>kursbeskrivelse</b> "for alle".';
WebIdentity::save($group,1,'default',$input);$assert(WebIdentity::entry($group)['title']==='Salsa & dans','Title save');
$identity=WebIdentity::read($group);update_option('rnl_web_version','1');WebIdentity::upgrade();
$assert(WebIdentity::read($group)===$identity,'Upgrade replaced an explicitly configured address or sharing fields');
$denied(fn()=>WebIdentity::save($group,1,'default',$input));
$bad=$input;$bad['slug']=WebIdentity::entry($duplicate)['slug'];$denied(fn()=>WebIdentity::save($group,2,'default',$bad));
$bad=$input;$bad['image_id']=$room;$denied(fn()=>WebIdentity::save($group,2,'default',$bad));
wp_set_current_user(0);$denied(fn()=>WebIdentity::save($group,2,'default',$input));wp_set_current_user($admin);
$input['slug']='salsa-tredje-adresse';WebIdentity::save($group,2,'default',$input);
$entry=WebIdentity::entry($group);$assert(in_array($initial,$entry['aliases'],true)&&in_array('salsa-ny-adresse',$entry['aliases'],true),'Alias history missing');
$input['slug']=$initial;WebIdentity::save($group,3,'default',$input);$assert(!in_array($initial,WebIdentity::entry($group)['aliases'],true),'Alias loop');
$bad=WebIdentity::entry($duplicate);$bad['slug']='salsa-ny-adresse';$denied(fn()=>WebIdentity::save($duplicate,1,'default',$bad));
$repo->groupLifecycle($duplicate,$repo->get($duplicate)['version'],$repo->get($period)['version'],false);
$repo->publish($repo->previewPublication($period,$repo->get($period)['version'],true));
$copy=$created[]=$repo->copyPeriod($period,$repo->get($period)['version'],$p['title'],'2031-09-01');
foreach(array_keys($repo->groups($copy)) as $copyGroup){$created[]=$copyGroup;}
$assert(WebIdentity::entry($copy)['slug']!==WebIdentity::entry($period)['slug'],'Copy reused period slug');
$assert(WebIdentity::entry($copy)['aliases']===[],'Copy inherited source aliases');
$assert(get_post_status($copy)==='draft','Copy unexpectedly published');
$read=(new Catalog())->read();$url=PublicSite::url($group);
$assert(str_contains($url,'/kursrekke/'.$initialPeriod.'/'.$initial),'Public URL not pretty');
$assert(PublicRoutes::resolve($read,$initialPeriod,'salsa-ny-adresse')['group']===$group,'Alias does not resolve');
$assert(PublicRoutes::resolve($read,'kurs-'.$period,'kurs-'.$group)['group']===$group,'Earlier ID-based course route no longer resolves');
$assert(PublicRoutes::resolve($read,WebIdentity::entry($other)['slug'],$initial)===null,'Wrong period exposes course');
$assert(PublicRoutes::resolve($read,$initialPeriod,WebIdentity::entry($duplicate)['slug'])===null,'Draft exposes slug');
$assert(!str_contains(wp_json_encode($read),'aliases')&&!str_contains(wp_json_encode($read),'history'),'Identity history leaks into catalog');
$schema=SchemaPresenter::group($read['groups'][$group],$url);
$assert($schema['hasCourseInstance']['location']['geo']['latitude']===59.91,'Course geo missing');
$event=$schema['hasCourseInstance']['subEvent'][0];
$assert($event['location']['geo']['longitude']===10.75&&$event['location']['address']['@type']==='PostalAddress','Session location missing');
$assert(str_starts_with($event['@id'],$url.'#session-'),'Session identity wrong');
$assert(!str_contains(wp_json_encode($schema),'InStock'),'Unverified stock claim');
$GLOBALS['wp_query']=new WP_Query(['page_id'=>$page]);$_GET=['rnl_course'=>(string)$group];
$data=SharingMetadata::data();$assert($data['title']==='Salsa & dans – '.get_bloginfo('name'),'Custom title missing');
$assert($data['description']==='Trygg kursbeskrivelse "for alle".','Description markup not removed');
$images=[];foreach(['global','period','group'] as $name){
 $id=$created[]=wp_insert_attachment(['post_title'=>'M41 '.$name,'post_status'=>'inherit','post_mime_type'=>'image/png'],'m41-'.$name.'.png');
 update_post_meta($id,'_wp_attached_file','m41-'.$name.'.png');wp_update_attachment_metadata($id,['width'=>100,'height'=>100,'file'=>'m41-'.$name.'.png']);update_post_meta($id,'_wp_attachment_image_alt','Alt '.$name);$images[$name]=$id;
}
update_option('rnl_sharing_image',$images['global']);$assert(SharingMetadata::data()['image']['alt']==='Alt global','Global fallback');
$e=WebIdentity::entry($period);$e['image_id']=$images['period'];WebIdentity::save($period,1,'default',$e);$assert(SharingMetadata::data()['image']['alt']==='Alt period','Period fallback');
$e=WebIdentity::entry($group);$e['image_id']=$images['group'];WebIdentity::save($group,4,'default',$e);$assert(SharingMetadata::data()['image']['alt']==='Alt group','Group image priority');
$_GET['utm_campaign']='synthetic';$_GET['rnl_day']='1';ob_start();SharingMetadata::head();$head=ob_get_clean();
$assert(substr_count($head,'rel="canonical"')===1&&!str_contains($data['url'],'utm_'),'Canonical polluted');
$assert(str_contains($head,'noindex')&&str_contains($head,'og:image:alt')&&str_contains($head,'twitter:card'),'Metadata missing');
$assert(str_contains($head,'Salsa &amp; dans'),'Metadata escaping');
$assert(apply_filters('wpseo_frontend_presenters',['placeholder'])===[],'Yoast duplicate presenters');
$assert(in_array($url,array_column((new CourseSitemap())->get_url_list(1),'loc'),true),'Sitemap course missing');
$translated=static fn($v)=>['en'=>['native_name'=>'English','default_locale'=>'en_US']];
$object=static fn($id,$type,$fallback=true,$lang=null)=>$lang==='en'?$page:$id;
$permalink=static fn($v,$lang)=>$lang==='en'?str_replace('/kursrekke/','/en/kursrekke/',$v):$v;
add_filter('wpml_active_languages',$translated,10);add_filter('wpml_object_id',$object,10,4);add_filter('wpml_permalink',$permalink,10,2);
$e=WebIdentity::entry($group);$e['slug']='salsa-intermediate';WebIdentity::save($group,5,'en',$e);
$assert(PublicRoutes::url(0,null,'en')===home_url('/en/kursrekke/'),'Overview URL ignores language');
$assert(str_contains(PublicRoutes::url($period,$group,'en'),'/en/kursrekke/'.$initialPeriod.'/salsa-intermediate'),'Language-specific URL');
$assert(PublicRoutes::resolve($read,$initialPeriod,'salsa-intermediate','en')['group']===$group,'Language resolution');
ob_start();SharingMetadata::head();$head=ob_get_clean();$assert(str_contains($head,'hreflang="en-US"')&&str_contains($head,'salsa-intermediate'),'hreflang missing');
remove_filter('wpml_active_languages',$translated,10);remove_filter('wpml_object_id',$object,10);remove_filter('wpml_permalink',$permalink,10);
$collision=$created[]=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>'kursrekke','post_title'=>'Existing unrelated section']);
$assert(!PublicRoutes::enabled(),'Existing WordPress section was taken over');
$assert(!isset(get_option('rewrite_rules')['^kursrekke/?$']),'Root rule takes over existing page');
$assert(PublicSite::url()===get_permalink($page),'Collision loses overview fallback');
$assert(str_contains(PublicSite::url($group),'rnl_course='),'Collision lost legacy fallback');
wp_delete_post($collision,true);
$assert(PublicRoutes::enabled(),'Routes did not recover after collision removed');
$state=get_post_meta($period,ContentTypes::META,true);$state['data']['visible_until']='2020-02-01T00:00:00Z';update_post_meta($period,ContentTypes::META,wp_slash($state));
$assert(PublicRoutes::resolve((new Catalog())->read(),$initialPeriod,'salsa-ny-adresse')===null,'Expired alias leaks');
$assert(SharingMetadata::data()===null,'Expired metadata leaks');
$assert(!in_array($url,array_column((new CourseSitemap())->get_url_list(1),'loc'),true),'Expired sitemap leaks');
WP_CLI::success('M4.1 WordPress controls passed: '.$checks);
}finally{
 PublicRoutes::$selection=null;$_GET=$oldGet;$GLOBALS['wp_query']=$oldQuery;wp_set_current_user($admin);
 foreach(array_reverse($created)as$id){wp_delete_post($id,true);}foreach($options as$key=>$v){if($v===null)delete_option($key);else update_option($key,$v);}flush_rewrite_rules(false);wp_set_current_user($original);
}
