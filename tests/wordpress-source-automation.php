<?php
use RegiNor\Lite\Infrastructure\{CourseRepository,ContentTypes,LetsRegConnection as Connection,LetsRegMapping as Mapping,LetsRegChanges as Changes,ImportedDescriptions as Descriptions,LetsRegAvailabilityStore as Availability,Mutation};
use RegiNor\Lite\Infrastructure\CourseAutomation as Automation;
use RegiNor\Lite\Admin\{LetsRegChangesPanel as Panel,CoursePage};
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local')throw new RuntimeException('Local only');
$created=[];$users=[];$checks=0;$calls=0;$env=[];$oldUser=get_current_user_id();$oldGet=$_GET;$failure=false;
$old=[];foreach([Connection::OPTION,'rnl_letsreg_poll','rnl_sales_history_enabled']as$key)$old[$key]=get_option($key,null);
foreach(['AFFILIATE_ID','ORGANIZER_ID','USERNAME','PASSWORD','CLIENT_ID']as$key){$name='RNL_LETSREG_'.$key;if(defined($name))throw new RuntimeException('Tests require environment variables');$env[$name]=getenv($name);}
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];$repo=new CourseRepository();
$assert=static function($ok,$why)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($why);};
$reject=static function($fn,$code=0)use($assert){try{$fn();}catch(Throwable $e){$assert(!$code||$e->getCode()===$code,'Wrong error: '.$e->getMessage());return;}$assert(false,'Invalid operation accepted');};
$text='Lær salsa fra bunnen av. Vi øver på grunntrinn, rytme, samspill og enkle kombinasjoner i et hyggelig miljø med god tid til spørsmål og repetisjon.';
$priceAmount=100;$mail=[];$mailOK=true;
$mailHook=static function($pre,$args)use(&$mail,&$mailOK){if(Mutation::active())throw new RuntimeException("Mail inside transaction");$mail[]=$args;return $mailOK;};
add_filter("pre_wp_mail",$mailHook,PHP_INT_MAX,2);
$event=['id'=>12345,'organizer'=>['id'=>42,'affiliateId'=>7],'name'=>'Salsa nybegynner','description'=>$text,'active'=>true,'published'=>true,'isCancelled'=>false,'lastUpdate'=>'2026-09-21T12:00:00Z','eventUrl'=>'https://www.letsreg.com/event/changes','startDate'=>'2031-01-06T18:00:00+01:00','endDate'=>'2031-01-13T19:00:00+01:00'];
$http=static function($pre,$args,$url)use(&$calls,&$event,&$failure,&$priceAmount){++$calls;if(Mutation::active())throw new RuntimeException('Network under repository lock');
 if($failure && str_contains($url,'/events/'))return ['response'=>['code'=>503],'headers'=>[],'body'=>''];
 $data=match($url){Connection::TOKEN_URL=>['access_token'=>'synthetic.changes.token','token_type'=>'bearer','expires_in'=>3600],Connection::ORIGIN.'/organizers/42'=>['id'=>42,'affiliateId'=>7,'name'=>'Synthetic'],Connection::ORIGIN.'/events/12345'=>$event,Connection::ORIGIN.'/events/12346'=>array_replace($event,['id'=>12346]),Connection::ORIGIN.'/events/12345/prices',Connection::ORIGIN.'/events/12346/prices'=>[['id'=>11,'name'=>'Fører','active'=>true,'price'=>$priceAmount]],default=>throw new RuntimeException('Real request blocked')};
 return ['response'=>['code'=>200],'headers'=>['content-type'=>'application/json'],'body'=>wp_json_encode($data)];};
add_filter('pre_http_request',$http,PHP_INT_MAX,3);
try{
 wp_set_current_user($admin);foreach(['AFFILIATE_ID'=>'7','ORGANIZER_ID'=>'42','USERNAME'=>'changes@example.invalid','PASSWORD'=>'synthetic-only','CLIENT_ID'=>'']as$key=>$value)putenv('RNL_LETSREG_'.$key.'='.$value);
 update_option('rnl_sales_history_enabled',false);delete_option(Connection::OPTION);delete_option('rnl_letsreg_poll');
 $venue=$created[]=$repo->create('venue',['title'=>'Changes venue','address'=>'Test']);$room=$created[]=$repo->create('room',['title'=>'Changes room','venue_id'=>$venue]);
 $periodData=['title'=>'Changes period','timezone'=>'Europe/Oslo','start_date'=>'2031-01-06','end_date'=>'2031-01-13','default_session_count'=>2,'default_room_id'=>$room,'default_price_minor'=>10000,'default_price_basis'=>'person','visible_from'=>'2030-01-01T00:00:00Z','visible_until'=>'2032-01-01T00:00:00Z','sales_from'=>'2030-01-01T00:00:00Z','sales_until'=>'2032-01-01T00:00:00Z','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[]];
 $period=static function()use($repo,$periodData,&$created){return $created[]=$repo->create('period',$periodData);};
 $select=static function($id=12345){delete_option(Connection::OPTION);Connection::checkCourse($id);$source=Mapping::source();return Mapping::build($id,$source['verification_id'],[11=>['role'=>'leader','registration'=>'single']]);};
 $fields=['weekday'=>1,'start_time'=>'18:00','end_time'=>'19:00','registration_status'=>'available','registration_url'=>'https://www.letsreg.com/event/changes'];
 $description=['title'=>'Salsa nybegynner','description'=>$text,'dance_style'=>'Salsa','level_description'=>'Ingen forkunnskaper','partner_info'=>''];
 $mapping=$select();$p1=$period();$proposal=$repo->previewImport($p1,1,0,$fields,$mapping,$description);
 $assert($proposal['description_plan']['action']==='create','First import not new');$g1=$created[]=$repo->confirmImport($proposal);$c1=$created[]=$repo->get($g1)['data']['course_id'];
 $assert(Changes::inspect($g1,$repo->get($g1)['data'])['state']==='unchanged','Import did not retain reviewed source');
 $manager=$users[]=wp_insert_user(['user_login'=>'rnl_auto_'.wp_generate_password(8,false),'user_email'=>'rnl-auto@example.invalid','user_pass'=>wp_generate_password(),'role'=>'rnl_course_manager']);
 $other=$users[]=wp_insert_user(['user_login'=>'rnl_other_'.wp_generate_password(8,false),'user_email'=>'rnl-other@example.invalid','user_pass'=>wp_generate_password(),'role'=>'subscriber']);
 $assert(!Automation::settings($g1)['time']&&!Automation::settings($g1)['email'],'Not opt in');
 $before=$repo->get($g1); Automation::process($g1);$assert($repo->get($g1)===$before && !$mail,'Disabled changes course');
 $reject(static fn()=>Automation::save($g1,$before['version'],['price'=>true,'category'=>999]));
 wp_update_post(['ID'=>$g1,'post_status'=>'publish']);wp_update_post(['ID'=>$p1,'post_status'=>'publish']);$parent=$repo->get($p1);$descriptionBefore=$repo->get($c1);
 Automation::save($g1,$repo->get($g1)['version'],['time'=>true,'price'=>true,'category'=>11,'email'=>true]);
 $assert($repo->get($g1)['version']===$before['version']+1,'Policy not versioned');
 $assert(Automation::settings($g1)['actor']===$admin,'Authority not recorded');
 $event['startDate']='2031-01-06T18:30:00+01:00';$event['endDate']='2031-01-13T19:30:00+01:00';$event['description'].=' Endret tekst.';$priceAmount=120;$select();
 $now=$repo->get($g1);$repo->applySourceAutomation($g1,$now['version'],Changes::hash(Changes::current($mapping)['snapshot']));
 $next=$repo->get($g1);$assert($next['data']['start_time']==='18:30' && $next['data']['price_minor']===12000,'Time/price not applied');
 $assert($next['data']['sessions'][0]['start_time']==='18:30' && $next['data']['sessions'][0]['starts_at']==='2031-01-06T17:30:00Z','Actual session UTC not updated');
 $assert($next['data']['sessions'][0]['id']===$now['data']['sessions'][0]['id'],'Session identity replaced');
 $assert($repo->get($c1)===$descriptionBefore && isset(Changes::inspect($g1,$next['data'])['changes']['description']),'Text auto approved');
 $assert($repo->get($p1)===$parent && get_post_status($p1)==='publish' && get_post_status($g1)==='publish','Publication changed');
 $assert(!$mail && get_post_meta($g1,Automation::NOTICE,true)['applied'],'Outbox not atomic or premature mail');
 Automation::process($g1);$assert(count($mail)===1 && $mail[0]['to']==='rnl-auto@example.invalid','Incorrect recipients or missing email');
 $assert(str_contains($mail[0]['message'],'Klokkeslett:')&&str_contains($mail[0]['message'],'manuell'),'Notification lacks change details');
 Automation::process($g1);$assert(count($mail)===1 && $repo->get($g1)===$next,'Repeated check changes course or duplicates mail');
 // Changed date is not a minor time update; price and schedule roll back together.
 $event['startDate']='2031-01-07T18:30:00+01:00';$priceAmount=130;$select();Automation::process($g1);
 $assert($repo->get($g1)===$next && str_contains(Automation::settings($g1)['status'],'Datoendring'),'Date change applied or unreported');
 $assert(count($mail)===2,'Blocked update not notified');Automation::process($g1);$assert(count($mail)===2,'Repeated block spams');
 $event['startDate']='2031-01-06T18:30:00+01:00';$select();Automation::process($g1);$assert($repo->get($g1)['data']['price_minor']===13000,'Recovered source not applied');
 // Collision prevents every local write and leaves source change pending.
 $p2=$period();$g2=$created[]=$repo->createGroup($p2,$c1,array_replace($fields,['start_time'=>'20:00','end_time'=>'21:00']));
 $repo->confirm($repo->previewGroup($g2,1,[]));
 $event['startDate']='2031-01-06T20:00:00+01:00';$event['endDate']='2031-01-13T21:00:00+01:00';$select();$before=$repo->get($g1);Automation::process($g1);
 $assert($repo->get($g1)===$before && str_contains(Automation::settings($g1)['status'],'kollisjon'),'Collision overwritten');wp_delete_post($g2,true);
 Automation::process($g1);$assert($repo->get($g1)['data']['start_time']==='20:00','Collision resolution did not retry');
 // Preserve sessions in the past, even when the source still changes the weekly time.
 $fixed=new class implements \RegiNor\Lite\Domain\Publication\Clock {public function now():DateTimeImmutable{return new DateTimeImmutable('2031-01-07T00:00:00Z');}};
 $timedRepo=new CourseRepository($fixed);$before=$repo->get($g1);$event['startDate']='2031-01-06T20:15:00+01:00';$event['endDate']='2031-01-13T21:15:00+01:00';$select();
 $timedRepo->applySourceAutomation($g1,$before['version'],Changes::hash(Changes::current($mapping)['snapshot']));$next=$repo->get($g1);
 $assert($next['data']['sessions'][0]===$before['data']['sessions'][0] && $next['data']['sessions'][1]['start_time']==='20:15','Past session overwritten');
 // Explicit time exceptions and cancellations are preserved.
 $exception=$repo->get($g1);$exception['data']['sessions'][0]['status']='cancelled';$exception['data']['sessions'][0]['reason']='Synthetic cancellation';
 $exception['data']['sessions'][1]['end_time']='22:00';$exception['data']['sessions'][1]['ends_at']='2031-01-13T21:00:00Z';Automation::put($g1,ContentTypes::META,$exception);
 $event['startDate']='2031-01-06T20:30:00+01:00';$event['endDate']='2031-01-13T21:30:00+01:00';$select();
 $repo->applySourceAutomation($g1,$exception['version'],Changes::hash(Changes::current($mapping)['snapshot']));$next=$repo->get($g1);
 $assert($next['data']['sessions']===$exception['data']['sessions'],'Cancelled or custom time overwritten');
 // Stale and failed checks may not apply data. Disabled settings can still be saved.
 $failure=true;delete_option(Connection::OPTION);Connection::checkCourse(12345);$reject(static fn()=>$repo->applySourceAutomation($g1,$next['version'],Changes::hash(Changes::current($mapping)['snapshot'])),409);
 Automation::save($g1,$next['version'],[]);$assert(!Automation::settings($g1)['time'],'Cannot disable during API outage');$failure=false;$select();
 wp_set_current_user($other);$reject(static fn()=>Automation::save($g1,$repo->get($g1)['version'],['email'=>true]),403);wp_set_current_user($admin);
 // Email-only mode never changes the course; failed transport retries without real mail.
 Automation::save($g1,$repo->get($g1)['version'],['email'=>true]);$before=$repo->get($g1);$event['description'].=' Nytt avsnitt.';$select();$mailOK=false;$count=count($mail);Automation::process($g1);
 $assert(count($mail)===$count+1 && $repo->get($g1)===$before,'Email-only updates content');Automation::process($g1);$assert(count($mail)===$count+1,'Retry backoff ignored');
 $notice=get_post_meta($g1,Automation::NOTICE,true);$notice['retry_at']=0;Automation::put($g1,Automation::NOTICE,$notice);$mailOK=true;Automation::process($g1);$assert(count($mail)===$count+2,'Failed email not retried');
 // A manual price survives idle polls and blocks later automatic replacement.
 Automation::save($g1,$repo->get($g1)['version'],['price'=>true,'category'=>11]);
 $manual=$repo->get($g1);$manual['data']['price_minor']=99900;Automation::put($g1,ContentTypes::META,$manual);
 Automation::process($g1);$priceAmount=145;$select();Automation::process($g1);
 $assert($repo->get($g1)['data']['price_minor']===99900&&str_contains(Automation::settings($g1)['status'],'lokalt'),'Idle poll forgets manual override');
 // Explicit parkategori price is per participant; local per-pair display needs two.
 $manual=$repo->get($g1);$manual['data']['price_basis']='pair';$manual['data']['letsreg_mapping']['categories'][0]['registration']='pair';Automation::put($g1,ContentTypes::META,$manual);
 Automation::save($g1,$manual['version'],['price'=>true,'category'=>11]);$priceAmount=150;$select();Automation::process($g1);
 $assert($repo->get($g1)['data']['price_minor']===30000,'Pair price not converted from per participant');
 // UI dispatch requires both nonce and current course version.
 $payload=['operation'=>'settings','id'=>(string)$g1,'version'=>(string)$repo->get($g1)['version'],'automation'=>['email'=>'1'],'_wpnonce'=>'bad'];
 $reject(static fn()=>Panel::dispatch($payload),403);$payload['_wpnonce']=wp_create_nonce('rnl_letsreg_review');Panel::dispatch($payload);$reject(static fn()=>Panel::dispatch($payload),409);
 ob_start();Panel::render($g1,$repo->get($g1));$html=ob_get_clean();$assert(str_contains($html,'automation[price]')&&str_contains($html,'alle kursansvarlige'),'Missing policy controls');
 // Approved text produces an outbox event, while mail stays outside its transaction.
 $fresh=$repo->get($g1);$review=Changes::inspect($g1,$fresh['data']);$repo->reviewLetsRegSource($g1,$fresh['version'],$repo->get($c1)['version'],Changes::hash($review['latest']['snapshot']),'description');
 $notice=get_post_meta($g1,Automation::NOTICE,true);$assert(str_contains(implode(' ',$notice['applied']),'manuelt'),'Manual approval not notified');
 // Revoked authority prevents background writes and is restored after worker.
 $config=Automation::settings($g1);$config['actor']=$other;Automation::put($g1,Automation::META,$config);$before=$repo->get($g1);Automation::process($g1);$assert($repo->get($g1)===$before&&get_current_user_id()===$admin,'Revoked authority used or actor leaked');
}finally{
 remove_filter('pre_http_request',$http,PHP_INT_MAX);remove_filter('pre_wp_mail',$mailHook,PHP_INT_MAX);wp_set_current_user($admin);
 foreach(array_reverse(array_unique($created))as$id)wp_delete_post($id,true);
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$id)if(!is_wp_error($id))wp_delete_user($id);
 foreach($old as$key=>$value){if($value===null)delete_option($key);else update_option($key,$value);}
 foreach($env as$key=>$value)putenv($value===false?$key:$key.'='.$value);
 wp_set_current_user($oldUser);$_GET=$oldGet;
}
WP_CLI::success("$checks automation checks passed; no real email or API traffic.");
