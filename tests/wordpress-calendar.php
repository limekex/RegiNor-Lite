<?php
use RegiNor\Lite\Infrastructure\{CourseRepository,ContentTypes,WebIdentity,CourseCalendar,Mutation};
use RegiNor\Lite\Frontend\{Catalog,CalendarEndpoint,CourseTools,PublicSite};
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local tests only'); }
$created=[];$checks=0;$original=get_current_user_id();$oldPage=get_option('rnl_course_page_id',null);$oldGet=$_GET;
$assert=static function($ok,$message)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($message);};
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];$repo=new CourseRepository();
try {
wp_set_current_user($admin);
$page=$created[]=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Calendar tests','post_content'=>'[reginor_courses]']);update_option('rnl_course_page_id',$page);
$venue=$created[]=$repo->create('venue',['title'=>'Dansested','address'=>'Testgata 12']);
$room=$created[]=$repo->create('room',['title'=>'Sal A','venue_id'=>$venue]);
$course=$created[]=$repo->create('course',['title'=>'Salsa Øvet','description'=>'Lær å danse.','level_description'=>'Litt erfaring.','dance_style'=>'Salsa','partner_info'=>'Kom alene.']);
$p=['title'=>'Høst 2030 kalender','timezone'=>'Europe/Oslo','start_date'=>'2030-10-14','default_session_count'=>3,'default_room_id'=>$room,'default_price_minor'=>120000,'default_price_basis'=>'person','visible_from'=>'2020-01-01T00:00:00Z','visible_until'=>'2031-01-01T00:00:00Z','sales_from'=>'2020-01-01T00:00:00Z','sales_until'=>'2031-01-01T00:00:00Z','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[['id'=>wp_generate_uuid4(),'from'=>'2030-11-04','until'=>'2030-11-04','reason'=>'Kursfri kveld']]];
$period=$created[]=$repo->create('period',$p);
$group=$created[]=$repo->createGroup($period,$course,['weekday'=>1,'first_date'=>'2030-10-21','start_time'=>'18:30','end_time'=>'20:00','registration_status'=>'available','registration_url'=>'https://www.letsreg.com/event/test']);
$repo->confirm($repo->previewGroup($group,1,[]));
$assert(CourseCalendar::read($group,'default')===null,'Draft calendar leaked');
$publish=static fn()=>$repo->publish($repo->previewPublication($period,$repo->get($period)['version'],true));
$draft=static fn()=>$repo->lifecycle($period,$repo->get($period)['version'],array_map(static fn($s)=>$s['version'],$repo->groups($period)),'draft');
$fail=static fn($result,$id,$key)=>$key===CourseCalendar::META?false:$result;
add_filter('update_post_metadata',$fail,10,3);
try{$publish();$assert(false,'Expected failed calendar commit');}catch(RuntimeException $error){$assert(str_contains($error->getMessage(),'Kalenderoppdateringen'),'Wrong rollback failure');}finally{remove_filter('update_post_metadata',$fail,10);}
$assert(get_post_status($period)==='draft'&&get_post_status($group)==='draft','Failed snapshot partially published');
$assert(get_post_meta($group,CourseCalendar::ID,true)===''&&get_post_meta($group,CourseCalendar::META,true)==='','Failed publication retained calendar data');
$publish();$initial=CourseCalendar::read($group,'default');$identity=$initial['identity'];$first=array_key_first($initial['events']);
$assert(count($initial['events'])===3,'Wrong session count');
$assert(!str_contains($initial['ics'],'DTSTART:20301014')&&!str_contains($initial['ics'],'DTSTART:20301104')&&str_contains($initial['ics'],'DTSTART:20301111'),'Delayed start or course break ignored');
$pastClock=new class implements \RegiNor\Lite\Domain\Publication\Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('2030-12-01T12:00:00Z');}};
Mutation::run(static fn()=>CourseCalendar::published(new Catalog($pastClock),$period),true);
$assert(CourseCalendar::read($group,'default')===$initial,'Past sessions dropped after end of course');
$assert(str_contains($initial['ics'],'DTSTART:20301021T163000Z')&&str_contains($initial['ics'],'DTSTART:20301028T173000Z'),'DST/shifted time wrong');
$assert(str_contains($initial['ics'],'DTEND:20301021T180000Z'),'90 minute session lost');
$assert($initial===CourseCalendar::read($group,'default'),'Unchanged fetch revises calendar');
$stored=get_post_meta($group,CourseCalendar::META,true);$assert($stored['default']===$initial['events'],'Publication did not persist minimal snapshot');
$assert(!str_contains(wp_json_encode($stored),'actor_id')&&!str_contains(wp_json_encode($stored),'price_minor'),'Private aggregate stored in calendar');
$_GET=['gclid'=>'private-ad','utm_source'=>'private-source','_gl'=>'private-linker'];$g=(new Catalog())->read()['groups'][$group];
ob_start();CourseTools::share($g);CourseTools::calendar($g);$html=ob_get_clean();
foreach(['private-ad','private-source','private-linker']as$secret){$assert(!str_contains($html,$secret),'Share leaked incoming identifier');}
$assert(str_contains($html,'Legg til i kalender')&&str_contains($html,'Last ned kalenderfil')&&str_contains($html,'Facebook')&&str_contains($html,'WhatsApp'),'Missing actions');
$assert(!str_contains($html,'<script')&&!str_contains($html,'<iframe'),'Third party embed');
$feed=CourseCalendar::url($identity,'default');$q=['rnl_calendar'=>$identity,'rnl_calendar_language'=>'default'];
$assert(CalendarEndpoint::response($q)['body']===$initial['ics'],'Feed differs from projection');
$download=CalendarEndpoint::response($q+['rnl_calendar_download'=>'1']);
$assert($download['body']===$initial['ics']&&str_starts_with($download['headers']['Content-Disposition'],'attachment'),'Download mismatch');
$assert(CalendarEndpoint::response($q+['rnl_calendar_download'=>['1']])['status']===200,'Malformed download parameter unsafe');
$assert(CalendarEndpoint::response(['rnl_calendar'=>[$identity]])['status']===404,'Array identity accepted');
$assert(CalendarEndpoint::response(['rnl_calendar'=>$identity,'rnl_calendar_language'=>'invalid'])['status']===404,'Unknown language accepted');
$e=WebIdentity::entry($group);$e['slug']='kalender-ny-adresse-'.$group;WebIdentity::save($group,WebIdentity::read($group)['version'],'default',$e);
$renamed=CourseCalendar::read($group,'default');
$assert($renamed['identity']===$identity&&array_keys($renamed['events'])===array_keys($initial['events']),'Slug changes calendar identity');
$assert($renamed['events'][$first]['sequence']===$initial['events'][$first]['sequence']+1,'Changed canonical not revised');
$assert(CourseCalendar::url($identity,'default')===$feed,'Subscription address changed');
$before=$renamed;$repo->saveRegistrationStatus($group,$repo->get($group)['version'],'full');
$assert(CourseCalendar::read($group,'default')===$before,'Capacity/status changed teaching calendar');
$draft();$assert(CourseCalendar::read($group,'default')===null,'Draft with snapshot exposed');
$assert(get_post_meta($group,CourseCalendar::META,true)['default']===$before['events'],'Draft erased prior public snapshot');
$proposal=$repo->previewSessionChange($group,$repo->get($group)['version'],$first,['start_time'=>'19:00','end_time'=>'20:30','status'=>'moved','reason'=>'En halvtime senere.']);
$repo->confirm($proposal);$publish();$moved=CourseCalendar::read($group,'default');
$assert($moved['identity']===$identity&&isset($moved['events'][$first]),'Moved UID lost');
$assert(str_contains($moved['ics'],'DTSTART:20301021T170000Z'),'Moved time missing');
$assert($moved['events'][$first]['sequence']>$before['events'][$first]['sequence'],'Moved revision missing');
// Remove a previously published, still-future session; tombstone must survive republication.
$draft();$repo->confirm($repo->previewGroup($group,$repo->get($group)['version'],['session_count'=>2]));$publish();
$removed=CourseCalendar::read($group,'default');
$assert(count($removed['events'])===3,'Removed session disappeared from feed');
$assert(count(array_filter($removed['events'],static fn($e)=>$e['cancelled']))===1,'Removed session not cancelled');
$repo->saveRegistrationStatus($group,$repo->get($group)['version'],'cancelled');$cancelled=CourseCalendar::read($group,'default');
$assert(count(array_filter($cancelled['events'],static fn($e)=>$e['cancelled']))===3,'Course cancellation missing');
$assert(CourseCalendar::read($group,'default')===$cancelled,'Repeated cancellation increments sequence');
$copy=$created[]=$repo->copyPeriod($period,$repo->get($period)['version'],'Kopi kalender','2032-10-18');foreach(array_keys($repo->groups($copy))as$id){$created[]=$id;$assert(get_post_meta($id,CourseCalendar::ID,true)==='','Copy reused calendar identity');}
$before=get_post_meta($group,CourseCalendar::META,true);
try{Mutation::run(static function()use($group){update_post_meta($group,CourseCalendar::META,['uncommitted'=>true]);Mutation::touch($group);throw new RuntimeException('rollback');},true);}catch(RuntimeException){}
$assert(get_post_meta($group,CourseCalendar::META,true)===$before,'Rollback retained calendar writes');
// WPML language selection is scoped; UID remains the same, unavailable translations are rejected.
$language='default';$current=static function()use(&$language){return $language;};$switch=static function($next)use(&$language){$language=$next;};
$languages=static fn()=>['en'=>['native_name'=>'English','default_locale'=>'en_US']];$objects=static fn($id,$type,$fallback=true,$code=null)=>$code==='en'?$page:$id;
$translate=static function($value,$context,$name)use(&$language,$group){return $language==='en'&&$name==='group.'.$group.'.title'?'Salsa intermediate':$value;};
add_filter('wpml_current_language',$current);add_action('wpml_switch_language',$switch);add_filter('wpml_active_languages',$languages);add_filter('wpml_object_id',$objects,10,4);add_filter('wpml_translate_single_string',$translate,10,3);
$english=CourseCalendar::read($group,'en');$assert($english['identity']===$identity&&count($english['events'])===3,'Language lost identity/tombstones');$assert(str_contains($english['ics'],'Salsa intermediate'),'Translated title missing');$assert($language==='default','Language context leaked');
remove_filter('wpml_current_language',$current);remove_action('wpml_switch_language',$switch);remove_filter('wpml_active_languages',$languages);remove_filter('wpml_object_id',$objects,10);remove_filter('wpml_translate_single_string',$translate,10);
$state=get_post_meta($period,ContentTypes::META,true);$state['data']['visible_until']='2020-02-01T00:00:00Z';update_post_meta($period,ContentTypes::META,wp_slash($state));
$assert(CalendarEndpoint::response($q)['status']===404,'Expired calendar leaked');
$assert(CalendarEndpoint::response($q+['rnl_calendar_download'=>'1'])['status']===404,'Expired download leaked');
$assert(CalendarEndpoint::response(['rnl_calendar'=>'00000000-0000-4000-8000-000000000000'])['body']==='Not found.','Unknown response leaks');
WP_CLI::success('Calendar and sharing controls passed: '.$checks);
} finally {
$_GET=$oldGet;wp_set_current_user($admin);foreach(array_reverse($created)as$id){wp_delete_post($id,true);}if($oldPage===null)delete_option('rnl_course_page_id');else update_option('rnl_course_page_id',$oldPage);wp_set_current_user($original);
}
