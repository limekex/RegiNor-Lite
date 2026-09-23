<?php
use RegiNor\Lite\Infrastructure\{CourseRepository,RegistrationState,RichText};
use RegiNor\Lite\Frontend\{Catalog,Renderer,SchemaPresenter};
use RegiNor\Lite\Domain\Publication\Clock;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local only'); }
$created=[]; $checks=0; $original=get_current_user_id();
$assert=static function($ok,$why)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($why);};
$clock=new class implements Clock { public string $time='2030-01-02T12:00:00Z'; public function now():DateTimeImmutable{return new DateTimeImmutable($this->time);} };
$repo=new CourseRepository($clock);$catalog=new Catalog($clock);
$http=static function(){throw new RuntimeException('Local courses must not call provider');};
add_filter('pre_http_request',$http,PHP_INT_MAX);
try {
 wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]);
 $venue=$created[]=$repo->create('venue',['title'=>'Local venue','address'=>'Testgata']);
 $room=$created[]=$repo->create('room',['title'=>'Local room','venue_id'=>$venue]);
 $course=$created[]=$repo->create('course',['title'=>'Local description','description'=>'<p>Lær <strong>salsa</strong>.</p>','level_description'=>'<p>Alle nivåer.</p>','dance_style'=>'Salsa','partner_info'=>'']);
 $assert($repo->get($course)['data']['description']==='<p>Lær <strong>salsa</strong>.</p>','Storage strips formatting');
 $p=['title'=>'Local modes','timezone'=>'Europe/Oslo','start_date'=>'2030-01-07','default_session_count'=>3,'default_room_id'=>$room,'default_price_minor'=>90000,'default_price_basis'=>'person','visible_from'=>'2030-01-01T00:00:00Z','visible_until'=>'2030-03-01T00:00:00Z','sales_from'=>'2030-01-01T00:00:00Z','sales_until'=>'2030-02-01T00:00:00Z','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[]];
 $period=$created[]=$repo->create('period',$p);
 $drop=$created[]=$repo->createGroup($period,$course,['title'=>'Drop-in test','weekday'=>1,'start_time'=>'18:00','end_time'=>'19:00','registration_status'=>'dropin','registration_url'=>'','dropin_price_minor'=>15000]);
 $external=$created[]=$repo->createGroup($period,$course,['title'=>'Own link test','weekday'=>2,'start_time'=>'18:00','end_time'=>'19:00','registration_status'=>'external','registration_url'=>'https://example.org/signup?utm_campaign=local#form','dropin_price_minor'=>0]);
 foreach([$drop,$external]as$id){$repo->confirm($repo->previewGroup($id,1,[]));}
 $proposal=$repo->previewPublication($period,1,true);$assert(!$proposal['errors'],'Local courses cannot publish: '.implode(' / ',$proposal['errors']));$repo->publish($proposal);$clock->time='2030-01-08T12:00:00Z';
 $read=$catalog->read();$assert(str_contains($read['groups'][$drop]['description'], '<strong>salsa</strong>'), 'Public catalog translation strips stored formatting');$assert($read['groups'][$drop]['status']==='dropin'&&$read['groups'][$drop]['registration_url']==='','Drop-in inherits period sales or booking URL');
 $assert($read['groups'][$external]['status']==='external'&&str_contains($read['groups'][$external]['registration_url'],'utm_campaign=local#form'),'Own link not public or parameters lost');
 foreach(['list','week']as$view){
  $one=$read;$one['groups']=[$drop=>$read['groups'][$drop]];
  $html=(new Renderer())->render($one,['rnl_period'=>$period,'rnl_view'=>$view]);
  $assert(str_contains($html,'Kun drop-in')&&!str_contains($html,'Meld meg på'),'Drop-in overview has booking CTA or missing status');
 }
 $html=(new Renderer())->render($read,['rnl_course'=>$drop]);
 $assert(str_contains($html,'150 kr per person')&&str_contains($html,'per kurskveld')&&!str_contains($html,'900 kr')&&!str_contains($html,'Meld deg på'),'Drop-in detail displays full-course price or registration CTA');
 $html=(new Renderer())->render($read,['rnl_course'=>$external]);
 $assert(str_contains($html,'href="https://example.org/signup?utm_campaign=local#form" target="_blank"')&&!str_contains($html,'data-rnl-letsreg='),'Own link falsely counted as LetsReg click');
 $assert(!isset(SchemaPresenter::group($read['groups'][$drop],'https://example.org/drop')['hasCourseInstance']['offers']),'Drop-in advertises online full-course offer');
 $before=$repo->get($external);$parent=$repo->get($period);$next=$repo->saveRegistrationStatus($external,$before['version'],'dropin');
 $assert(get_post_status($external)==='publish'&&get_post_status($period)==='publish'&&$repo->get($period)===$parent&&$next['data']['sessions']===$before['data']['sessions'],'Status change unpublishes or reschedules course');
 $assert($catalog->read()['groups'][$external]['dropin_price_minor']===0,'Free drop-in not supported');
 $repo->saveRegistrationStatus($external,$next['version'],'external');
 $assert($catalog->read()['groups'][$external]['status']==='external','Cannot return to own link');
 $data=$repo->get($drop)['data'];$future=array_replace($p,['sales_from'=>'2031-01-01T00:00:00Z','sales_until'=>'2032-01-01T00:00:00Z']);
 $assert(RegistrationState::resolve($future,$data,$clock->now())['status']==='dropin','Drop-in blocked by future sales window');
 $assert(RegistrationState::resolve(array_replace($p,['cancelled'=>true]),$data,$clock->now())['status']==='cancelled','Drop-in ignores cancellation');
 $assert(RegistrationState::resolve($p,$data,new DateTimeImmutable('2031-01-01'))['status']==='ended','Drop-in ignores course end');
 try {$repo->saveRegistrationStatus($drop,$repo->get($drop)['version'],'external');$assert(false,'Missing own URL accepted');}catch(InvalidArgumentException $e){$assert(str_contains($e->getMessage(),'påmeldingslenke'),'Wrong own-link error');}
}finally{
 foreach(array_reverse($created)as$id)wp_delete_post($id,true);
 remove_filter('pre_http_request',$http,PHP_INT_MAX);wp_set_current_user($original);
}
WP_CLI::success("$checks local registration and rich storage checks passed; fixtures removed.");
