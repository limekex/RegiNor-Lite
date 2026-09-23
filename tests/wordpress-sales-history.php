<?php
use RegiNor\Lite\Infrastructure\{SalesHistory,JourneyStore,CourseRepository,ContentTypes,LetsRegConnection as Connection,Mutation,LetsRegAvailabilityStore as Availability};
use RegiNor\Lite\Admin\SalesHistoryPanel;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local') { throw new RuntimeException('Local only'); }
$created=[];$journeys=[];$checks=0;$calls=0;$oldUser=get_current_user_id();$oldGet=$_GET;$failure=false;$manager=0;
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
$options=[];foreach(['rnl_sales_history_enabled',Connection::OPTION,'rnl_letsreg_poll'] as $k){$options[$k]=get_option($k,null);}
$env=[];foreach(['AFFILIATE_ID','ORGANIZER_ID','USERNAME','PASSWORD','CLIENT_ID'] as $key){$name='RNL_LETSREG_'.$key;if(defined($name))throw new RuntimeException('Environment constants not supported in tests');$env[$name]=getenv($name);}
$assert=static function($ok,$msg)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($msg);};
$denied=static function($fn)use($assert){try{$fn();}catch(RuntimeException $e){$assert($e->getCode()===403,'Expected access denial');return;}$assert(false,'Unauthorized report operation');};
$totals=['registeredParticipants'=>4,'ordersTotalSum'=>1000];
$http=static function($pre,$args,$url)use(&$calls,&$totals,&$failure){
 ++$calls;if(Mutation::active())throw new RuntimeException('Network while holding course lock');
 if($failure && $url===Connection::ORIGIN.'/events/12345')return ['response'=>['code'=>503],'headers'=>[],'body'=>''];
 $data=match($url){
 Connection::TOKEN_URL=>['access_token'=>'synthetic.sales.token','token_type'=>'bearer','expires_in'=>3600],
 Connection::ORIGIN.'/organizers/42'=>['id'=>42,'affiliateId'=>7,'name'=>'Synthetic'],
 Connection::ORIGIN.'/events/12345'=>['id'=>12345,'organizer'=>['id'=>42,'affiliateId'=>7],'name'=>'Sales test','active'=>true,'published'=>true,'isCancelled'=>false,'eventUrl'=>'https://www.letsreg.com/event/sales-test']+$totals,
 Connection::ORIGIN.'/events/12345/prices'=>[['id'=>11,'name'=>'Fører','active'=>true,'price'=>250]],
 default=>throw new RuntimeException('Unexpected endpoint; no real requests allowed')};
 return ['response'=>['code'=>200],'headers'=>['content-type'=>'application/json'],'body'=>wp_json_encode($data)];
};
add_filter('pre_http_request',$http,PHP_INT_MAX,3);
try {
 wp_set_current_user($admin);foreach(['AFFILIATE_ID'=>'7','ORGANIZER_ID'=>'42','USERNAME'=>'sales@example.invalid','PASSWORD'=>'synthetic-only','CLIENT_ID'=>'']as$k=>$v)putenv('RNL_LETSREG_'.$k.'='.$v);
 delete_option(Connection::OPTION);delete_option('rnl_letsreg_poll');SalesHistory::install();JourneyStore::install();$repo=new CourseRepository();
 $venue=$created[]=$repo->create('venue',['title'=>'Sales test venue','address'=>'Test']);
 $room=$created[]=$repo->create('room',['title'=>'Sales test room','venue_id'=>$venue]);
 $course=$created[]=$repo->create('course',['title'=>'Sales test course','description'=>'Test','dance_style'=>'Salsa','level_description'=>'Test','partner_info'=>'']);
 $period=$created[]=$repo->create('period',['title'=>'Sales test period','timezone'=>'Europe/Oslo','start_date'=>'2031-01-06','visible_from'=>'2030-01-01T00:00:00Z','visible_until'=>'2032-01-01T00:00:00Z','sales_from'=>'2030-01-01T00:00:00Z','sales_until'=>'2031-02-01T00:00:00Z','default_session_count'=>2,'default_room_id'=>$room,'default_price_minor'=>25000,'default_price_basis'=>'person','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[]]);
 $group=$created[]=$repo->createGroup($period,$course,['weekday'=>1,'start_time'=>'18:00','end_time'=>'19:00']);
 $mapping=['affiliate_id'=>7,'organizer_id'=>42,'event_id'=>12345,'event_name'=>'Sales test','checked_at'=>time(),'verification_id'=>wp_generate_uuid4(),'categories'=>[['id'=>11,'name'=>'Fører','role'=>'leader','registration'=>'single','participants_per_selection'=>1]]];
 $state=$repo->get($group);$state['data']['registration_status']='available';$state['data']['letsreg_mapping']=$mapping;update_post_meta($group,ContentTypes::META,wp_slash($state));
 global $wpdb;$table=SalesHistory::table();$account=Connection::identity()['fingerprint'];
 update_option('rnl_sales_history_enabled',false);Connection::check(12345,null,true);
 $assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE account=%s",$account))===0,'Disabled history collected totals');
 update_option('rnl_sales_history_enabled',true);$assert(SalesHistoryPanel::refresh($group),'Admin refresh failed');
 $report=SalesHistory::report($group);$assert(count($report['observations'])===1,'First snapshot missing');
 $assert($report['observations'][0]['participants']===4 && $report['observations'][0]['order_sum_minor']===100000,'Source totals lost');
 $assert($report['observations'][0]['coincidence']['reason']==='baseline','First snapshot counted as sale');
 SalesHistoryPanel::refresh($group);$assert(count(SalesHistory::report($group)['observations'])===1,'Repeated check duplicated snapshot');
 $at=time();$wpdb->update($table,['observed_at'=>$at-600],['account'=>$account,'event_id'=>12345]);
 $journey=$journeys[]=wp_generate_uuid4();
 $click=['event_id'=>wp_generate_uuid4(),'journey_id'=>$journey,'stage'=>'letsreg_click','course_id'=>$group,'page_id'=>0,'campaign'=>['utm_source'=>'google','utm_medium'=>'cpc','utm_campaign'=>'synthetic-sales','utm_content'=>''],'click_ids'=>[]];
 JourneyStore::record($click);$wpdb->update(JourneyStore::table(),['created_at'=>gmdate('Y-m-d H:i:s',$at-300)],['event_id'=>$click['event_id']]);
 $totals=['registeredParticipants'=>6,'ordersTotalSum'=>1500];SalesHistoryPanel::refresh($group);$before=$calls;
 $report=SalesHistory::report($group);$last=end($report['observations']);
 $assert($last['change']['participants']===2 && $last['change']['order_sum_minor']===50000,'Delta incorrect');
 $assert($last['coincidence']['reason']==='one_candidate' && $last['coincidence']['journeys']===1,'Eligible click not compared');
 $assert($calls===$before,'Report calls provider');
 $_GET=['sales_course'=>(string)$group];ob_start();SalesHistoryPanel::render();$html=ob_get_clean();
 foreach(['Daglig oversikt','eksperimentelt','ikke bekreftet kobling','valuta','synthetic-sales']as$text){$assert(str_contains($html,$text),'Missing report explanation: '.$text);}
 $assert(!str_contains($html,'synthetic.sales.token')&&!str_contains($html,JourneyStore::hash($journey)),'Private tokens/identifiers rendered');
 $shared=$created[]=$repo->createGroup($period,$course,['weekday'=>2,'start_time'=>'18:00','end_time'=>'19:00']);
 $sharedState=$repo->get($shared);$sharedState['data']['letsreg_mapping']=$mapping;update_post_meta($shared,ContentTypes::META,wp_slash($sharedState));
 $second=$journeys[]=wp_generate_uuid4();$click2=array_replace($click,['event_id'=>wp_generate_uuid4(),'journey_id'=>$second,'course_id'=>$shared]);JourneyStore::record($click2);
 $wpdb->update(JourneyStore::table(),['created_at'=>gmdate('Y-m-d H:i:s',$at-100)],['event_id'=>$click2['event_id']]);
 $report=SalesHistory::report($group);$last=end($report['observations']);
 $assert(count($report['shared'])===2 && $last['coincidence']['journeys']===2,'Shared event clicks ignored or totals duplicated');
 JourneyStore::forget($second);$report=SalesHistory::report($group);$last=end($report['observations']);$assert($last['coincidence']['journeys']===1,'Withdrawal did not remove candidate');
 JourneyStore::forget($journey);$report=SalesHistory::report($group);$last=end($report['observations']);$assert($last['coincidence']['reason']==='no_clicks','Persisted inferred conversion survives withdrawal');
 // Changing account or mapping cannot inherit the previous account/category history.
 putenv('RNL_LETSREG_PASSWORD=changed');$assert(SalesHistory::report($group)['observations']===[],'Changed credentials reuse old observations');putenv('RNL_LETSREG_PASSWORD=synthetic-only');
 $changed=$state;$changed['data']['letsreg_mapping']['categories'][0]['id']=12;update_post_meta($group,ContentTypes::META,wp_slash($changed));
 $assert(SalesHistory::report($group)['observations']===[],'Remapped course inherits old scope');update_post_meta($group,ContentTypes::META,wp_slash($state));
 wp_set_current_user(0);$denied(static fn()=>SalesHistory::report($group));$denied(static fn()=>SalesHistoryPanel::refresh($group));wp_set_current_user($admin);
 $wpdb->update($table,['observed_at'=>$at-31*DAY_IN_SECONDS],['account'=>$account,'event_id'=>12345,'observed_at'=>$at-600]);SalesHistory::purge();
 $assert(count(SalesHistory::report($group)['observations'])===1,'Expired snapshots retained');
 $assert(!str_contains(wp_json_encode($report),'synthetic.sales.token'),'Token persisted');
 // Background polling also observes courses with a manual status when history is enabled.
 $wpdb->update($table,['observed_at'=>$at-600],['account'=>$account,'event_id'=>12345]);
 Availability::failure(Connection::identity(),12345);delete_option('rnl_letsreg_poll');$before=$calls;
 update_option('rnl_sales_history_enabled',false);Availability::runDue([$group]);
 $assert($calls>$before && count(SalesHistory::report($group)['observations'])===1,'Editorial checks must not record disabled sales history');
 Availability::failure(Connection::identity(),12345);delete_option('rnl_letsreg_poll');
 update_option('rnl_sales_history_enabled',true);$totals=['registeredParticipants'=>7,'ordersTotalSum'=>1750];Availability::runDue([$group]);
 $report=SalesHistory::report($group);$last=end($report['observations']);
 $assert($calls>$before && $last['participants']===7 && count($report['observations'])===2,'Background polling failed to record totals');
 $assert($repo->get($group)['data']['registration_status']==='available','History polling changed manual registration status');
 // A failed API control preserves history and shows that it is no longer a fresh status.
 $failure=true;Availability::failure(Connection::identity(),12345);delete_option('rnl_letsreg_poll');Availability::runDue([$group]);
 $assert(count(SalesHistory::report($group)['observations'])===2,'Failed control overwrote history');
 ob_start();SalesHistoryPanel::render();$html=ob_get_clean();$assert(str_contains($html,'Tallene er historiske'),'Failed control not indicated in report');$failure=false;
 $manager=wp_insert_user(['user_login'=>'rnl_sales_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'rnl_course_manager']);
 if(is_wp_error($manager))throw new RuntimeException('Test user could not be created');
 wp_set_current_user($manager);$denied(static fn()=>SalesHistory::report($group));$denied(static fn()=>SalesHistoryPanel::refresh($group));wp_set_current_user($admin);

} finally {
 remove_filter('pre_http_request',$http,PHP_INT_MAX);
 wp_set_current_user($admin);global $wpdb;
 if($manager && !is_wp_error($manager)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($manager);}
 if(isset($account))$wpdb->delete(SalesHistory::table(),['account'=>$account]);
 foreach($journeys as$j){$wpdb->delete(JourneyStore::table(),['journey'=>JourneyStore::hash($j)]);delete_transient('rnl_revoked_'.JourneyStore::hash($j));}
 foreach(array_reverse($created)as$id)wp_delete_post($id,true);
 foreach($options as$k=>$v){if($v===null)delete_option($k);else update_option($k,$v);}
 foreach($env as$k=>$v)putenv($v===false?$k:$k.'='.$v);
 wp_set_current_user($oldUser);$_GET=$oldGet;
}
WP_CLI::success("$checks sales history checks passed; synthetic data removed; no real API requests.");
