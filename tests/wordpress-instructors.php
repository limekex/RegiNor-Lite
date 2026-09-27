<?php
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Frontend\{Catalog,Renderer,InstructorCourses,PublicSite};
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Admin\CourseActions;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$created = []; $checks = 0; $user = get_current_user_id(); $manager = 0;
$options = ['rnl_instructor_types'=>get_option('rnl_instructor_types',null),'rnl_course_page_id'=>get_option('rnl_course_page_id',null)];
$assert = static function($ok,$message) use (&$checks) { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$reject = static function($fn) use ($assert) { try { $fn(); } catch (Throwable) { $assert(true,'rejected'); return; } $assert(false,'Invalid change accepted'); };
$clock = new class implements Clock { public string $date='2030-01-01T12:00:00Z'; public function now(): DateTimeImmutable { return new DateTimeImmutable($this->date); } };
$repo = new CourseRepository($clock); $shortcode = new InstructorCourses(new Catalog($clock));
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
try {
 wp_set_current_user($admin);
 register_post_type('rnl_test_teacher',['public'=>true]); update_option('rnl_instructor_types',['rnl_test_teacher']);
 $a=$created[]=wp_insert_post(['post_type'=>'rnl_test_teacher','post_status'=>'publish','post_title'=>'Teacher Alpha']);
 $b=$created[]=wp_insert_post(['post_type'=>'rnl_test_teacher','post_status'=>'publish','post_title'=>'Teacher Beta']);
 $translation=$created[]=wp_insert_post(['post_type'=>'rnl_test_teacher','post_status'=>'publish','post_title'=>'Teacher Beta EN']);
 $third=$created[]=wp_insert_post(['post_type'=>'rnl_test_teacher','post_status'=>'publish','post_title'=>'Teacher Beta ES']);
 $orphan=$created[]=wp_insert_post(['post_type'=>'rnl_test_teacher','post_status'=>'publish','post_title'=>'No main language profile']);
 $assert(count($repo->instructors())===5,'Without WPML, valid profiles disappeared');
 $defaultLanguage=static fn()=> 'nb';
 $profileLanguages=static function($id,$type,$fallback,$language=null) use ($b,$translation,$third,$orphan) {
     if ($language==='nb') { return $id===$orphan ? null : (in_array($id,[$b,$translation,$third],true) ? $b : $id); }
     return $id===$b ? $translation : $id;
 };
 add_filter('wpml_default_language',$defaultLanguage);
 add_filter('wpml_object_id',$profileLanguages,10,4);
 try {
     $choices=$repo->instructors();
     $assert(array_column($choices,'id')===[$a,$b],'Selector must show main profiles only, even while current language is English');
     $assert(array_column($choices,'title')===['Teacher Alpha','Teacher Beta'],'Selector translated main profile names');
     $assert(\RegiNor\Lite\Infrastructure\Wpml::defaultProfile($third)===$b,'Existing translated selection loses checked main profile');
     $assert(apply_filters('wpml_object_id',$b,'rnl_test_teacher',true)===$translation,'Frontend language resolution changed');
     wp_update_post(['ID'=>$b,'post_status'=>'draft']);
     $assert(array_column($repo->instructors(),'id')===[$a],'Published translation leaked when main profile is draft');
     wp_update_post(['ID'=>$b,'post_status'=>'publish']);
 } finally {
     remove_filter('wpml_default_language',$defaultLanguage);
     remove_filter('wpml_object_id',$profileLanguages,10);
 }
 $page=$created[]=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Teacher test courses']); update_option('rnl_course_page_id',$page);
 $venue=$created[]=$repo->create('venue',['title'=>'Teacher venue','address'=>'Testgata 1']);
 $room=$created[]=$repo->create('room',['title'=>'Teacher room','venue_id'=>$venue]);
 $room2=$created[]=$repo->create('room',['title'=>'Other room','venue_id'=>$venue]);
 $course=$created[]=$repo->create('course',['title'=>'Teacher description','description'=>'Test','level_description'=>'Test','dance_style'=>'Salsa','partner_info'=>'','audience'=>'mixed']);
 $period=$created[]=$repo->create('period',['title'=>'Teacher period','timezone'=>'Europe/Oslo','start_date'=>'2030-01-07','default_session_count'=>3,'default_room_id'=>$room,'default_price_minor'=>10000,'default_price_basis'=>'person','visible_from'=>'2020-01-01T00:00:00Z','visible_until'=>'2030-03-01T00:00:00Z','sales_from'=>'2020-01-01T00:00:00Z','sales_until'=>'2030-02-01T00:00:00Z','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[]]);
 $fields=['title'=>'Teacher course','weekday'=>1,'start_time'=>'18:00','end_time'=>'19:00','instructor_ids'=>[$a],'registration_status'=>'available','registration_url'=>'https://www.letsreg.com/event/test'];
 $group=$created[]=$repo->createGroup($period,$course,$fields);
 $other=$created[]=$repo->createGroup($period,$course,array_replace($fields,['title'=>'Other teacher course','room_id'=>$room2,'instructor_ids'=>[$b]]));
 foreach ([$group,$other] as $id) { $repo->confirm($repo->previewGroup($id,1,[])); }
 $state=$repo->get($group);
 $last=$state['data']['sessions'][2];
 $repo->confirm($repo->previewSessionChange($group,$state['version'],$last['id'],['status'=>'cancelled','reason'=>'Test cancellation']));
 $repo->publish($repo->previewPublication($period,1,true));
 $clock->date='2030-01-08T12:00:00Z';
 $before=$repo->get($group); $periodBefore=$repo->get($period);
 $reject(fn()=>$repo->saveInstructors($group,$before['version'],[$b]));
 $assert($repo->get($group)===$before,'Conflict changed data');
 $repo->saveInstructors($other,$repo->get($other)['version'],[]);
 $manager=wp_insert_user(['user_login'=>'rnl-instructor-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>'rnl_course_manager']);
 wp_set_current_user($manager);
 $actions=new CourseActions($repo);
 $result=$actions->handle(['command'=>'save_instructors','id'=>$group,'version'=>$before['version'],'_wpnonce'=>wp_create_nonce('rnl_course_command'),'data'=>['instructor_ids'=>[(string)$b]]]);
 $after=$repo->get($group);
 $assert($after['data']['sessions'][0]===$before['data']['sessions'][0],'Past session overwritten');
 $assert($after['data']['sessions'][2]===$before['data']['sessions'][2],'Cancelled session overwritten');
 $assert($after['data']['sessions'][1]['instructor_ids']===[$b],'Future teacher not updated');
 $assert(get_post_status($group)==='publish' && get_post_status($period)==='publish','Publication withdrawn');
 $assert($repo->get($period)===$periodBefore,'Period changed');
 $reject(fn()=>$repo->saveInstructors($group,$before['version'],[$a]));
 $reject(fn()=>$repo->saveInstructors($group,$after['version'],[$page]));
 $reject(fn()=>$actions->handle(['command'=>'save_instructors','id'=>$group,'version'=>$after['version'],'_wpnonce'=>'wrong']));
 wp_set_current_user(0);
 $reject(fn()=>$repo->saveInstructors($group,$after['version'],[]));
 $catalog=(new Catalog($clock))->read();
 $assert($catalog['groups'][$group]['instructors']===['Teacher Beta'],'Past teacher shown as current');
 $html=(new Renderer())->render($catalog,['rnl_course'=>$group]);
 $assert(str_contains($html,'rnl-instructor-profile') && str_contains($html,esc_url(get_permalink($b))),'Profile links missing');
 $html=(new Renderer())->render($catalog,['rnl_period'=>$period]);
 $assert(str_contains($html,'rnl-card-instructors'),'Card teacher text missing');
 $html=$shortcode->output(['instructor_id'=>$b]);
 $assert(str_contains($html,'Teacher course') && !str_contains($html,'Other teacher course'),'Instructor selection wrong');
 $assert(!str_contains($html,'<form') && !str_contains($html,'rnl-view-switch'),'Shortcode shows filters');
 global $wp_query;
 $oldQuery=$wp_query;
 try {
     $wp_query=new WP_Query(); $wp_query->queried_object=get_post($b); $wp_query->queried_object_id=$b;
     $assert(str_contains($shortcode->output(),'Teacher course'),'Automatic profile ID failed');
 } finally { $wp_query=$oldQuery; }
 $assert(PublicSite::containsCourses('[reginor_instructor_courses]'),'No early cache detection');
 $assert($shortcode->output(['instructor_id'=>$page])==='','Wrong post type accepted');
 $translate=static fn($id,$type)=>$id===$b ? $translation : $id;
 add_filter('wpml_object_id',$translate,10,2);
 $html=$shortcode->output(['instructor_id'=>$translation]);
 $assert(str_contains($html,'Teacher course') && str_contains($html,'Teacher Beta EN'),'Translated teacher lost courses');
 remove_filter('wpml_object_id',$translate,10);
 wp_set_current_user($admin);
 $extraPeriod=$created[]=$repo->create('period',array_replace($periodBefore['data'],['title'=>'Second teacher period','start_date'=>'2030-02-04','visible_until'=>'2030-04-01T00:00:00Z','sales_until'=>'2030-03-01T00:00:00Z']));
 $extraGroup=$created[]=$repo->createGroup($extraPeriod,$course,array_replace($fields,['title'=>'Second period teacher course','instructor_ids'=>[$b]]));
 $repo->confirm($repo->previewGroup($extraGroup,1,[]));
 $repo->publish($repo->previewPublication($extraPeriod,1,true));
 $html=$shortcode->output(['instructor_id'=>$b]);
 $assert(str_contains($html,'Teacher course') && str_contains($html,'Second period teacher course'),'Shortcode excludes other public periods');
 wp_update_post(['ID'=>$extraPeriod,'post_status'=>'draft']);
 $assert(!str_contains($shortcode->output(['instructor_id'=>$b]),'Second period teacher course'),'Hidden period leaked');
 $added=$repo->saveInstructors($group,$after['version'],[$a,$b]);
 $assert($added['data']['sessions'][1]['instructor_ids']===[$a,$b],'Cannot add second teacher');
 $same=$repo->saveInstructors($group,$added['version'],[$a,$b]);
 $assert($same['version']===$added['version'],'No-op changes version');
 wp_update_post(['ID'=>$translation,'post_password'=>'secret']);
 $reject(fn()=>$repo->saveInstructors($group,$added['version'],[$translation]));
 $repo->saveInstructors($group,$added['version'],[]);
 $assert($repo->get($group)['data']['instructor_ids']===[],'Cannot remove all teachers');
 $assert(!str_contains($shortcode->output(['instructor_id'=>$b]),'Teacher course'),'Removed teacher still has course');
} finally {
 wp_set_current_user($admin);
 foreach(array_reverse($created) as $id) { wp_delete_post($id,true); }
 foreach($options as $key=>$value) { if($value===null) { delete_option($key); } else { update_option($key,$value); } }
 if($manager && !is_wp_error($manager)) { require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($manager); }
 unregister_post_type('rnl_test_teacher'); wp_set_current_user($user);
}
WP_CLI::success('Instructor checks: '.$checks.'. Testdata ryddet.');
