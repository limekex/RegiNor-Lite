<?php
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\LetsRegConnection as Connection;
use RegiNor\Lite\Infrastructure\LetsRegMapping as Mapping;
use RegiNor\Lite\Infrastructure\Mutation;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Admin\LetsRegCoursePicker as Picker;
use RegiNor\Lite\Admin\CoursePage;
use RegiNor\Lite\Admin\CourseActions;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$env=[];
foreach (['AFFILIATE_ID','ORGANIZER_ID','USERNAME','PASSWORD','CLIENT_ID'] as $key) {
    $name='RNL_LETSREG_'.$key;
    if (defined($name)) throw new RuntimeException('Testen krever miljøvariabler, ikke konstante API-verdier.');
    $env[$name]=getenv($name);
}
$old=get_option(Connection::OPTION,null); $oldUser=get_current_user_id(); $oldGet=$_GET;
$created=[]; $users=[]; $calls=0; $checks=0;
$assert=static function($ok,$message) use (&$checks) { ++$checks; if (!$ok) throw new RuntimeException($message); };
$reject=static function(callable $fn,int $code=0,array $details=[]) use ($assert) { try {$fn();} catch (Throwable $error) {$assert(!$code || $error->getCode()===$code, 'Unexpected failure: '.$error->getMessage()); foreach ($details as $detail) { $assert(str_contains($error->getMessage(),$detail),'Missing actionable error detail: '.$detail); } return;} $assert(false,'Invalid import accepted'); };
$http=static function($pre,$args,$url) use (&$calls) {
    if (Mutation::active()) throw new RuntimeException('HTTP under course lock');
    ++$calls;
    $event=['id'=>12345,'organizer'=>['id'=>42,'affiliateId'=>7],'name'=>'Salsa import test','active'=>true,'published'=>true,'isCancelled'=>false,
        'description'=>'<p>Lær &amp; dans</p><p>Ny linje<br>Videre</p><script>alert(1)</script><img src=x onerror=alert(2) />','eventUrl'=>'https://www.letsreg.com/event/import-test','startDate'=>'2031-01-06T18:30:00+01:00','endDate'=>'2031-02-10T20:00:00+01:00'];
    $data=str_starts_with($url,Connection::ORIGIN.'/organizers/42/events?') ? [$event] : match($url) {
        Connection::TOKEN_URL=>['access_token'=>'synthetic.import.token','token_type'=>'bearer','expires_in'=>3600],
        Connection::ORIGIN.'/organizers/42'=>['id'=>42,'affiliateId'=>7,'name'=>'Synthetic import organizer'],
        Connection::ORIGIN.'/events/12345'=>$event,
        Connection::ORIGIN.'/events/12346'=>array_replace($event,['id'=>12346,'name'=>'Second import']),
        Connection::ORIGIN.'/events/12347'=>array_replace($event,['id'=>12347,'description'=>null]),
        Connection::ORIGIN.'/events/12345/prices',Connection::ORIGIN.'/events/12346/prices',Connection::ORIGIN.'/events/12347/prices'=>[['id'=>11,'name'=>'Fører','active'=>true,'price'=>950],['id'=>12,'name'=>'Følger','active'=>true,'price'=>950]],
        default=>throw new RuntimeException('Unexpected external request intercepted'),
    };
    return ['response'=>['code'=>200],'headers'=>['content-type'=>'application/json'],'body'=>wp_json_encode($data)];
};
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]; $repo=new CourseRepository();
add_filter('pre_http_request',$http,PHP_INT_MAX,3);
try {
    wp_set_current_user($admin);
    foreach (['AFFILIATE_ID'=>'7','ORGANIZER_ID'=>'42','USERNAME'=>'import@example.invalid','PASSWORD'=>'synthetic-only','CLIENT_ID'=>''] as $key=>$value) putenv('RNL_LETSREG_'.$key.'='.$value);
    $level=$created[]=$repo->create('level',['title'=>'import test','description'=>'','sort_order'=>10,'active'=>true]);
    $venue=$created[]=$repo->create('venue',['title'=>'Import venue','address'=>'Testgata 1']);
    $room=$created[]=$repo->create('room',['title'=>'Import room','venue_id'=>$venue]);
    $course=$created[]=$repo->create('course',['title'=>'Import description','description'=>'Learn salsa','level_description'=>'Beginners','dance_style'=>'Salsa','partner_info'=>'']);
    $period=$created[]=$repo->create('period',['title'=>'Import period','timezone'=>'Europe/Oslo','start_date'=>'2031-01-06','end_date'=>'2031-02-10',
        'default_session_count'=>5,'default_room_id'=>$room,'default_price_minor'=>95000,'default_price_basis'=>'person',
        'visible_from'=>'2030-01-01T00:00:00Z','visible_until'=>'2032-01-01T00:00:00Z','sales_from'=>'2030-01-01T00:00:00Z','sales_until'=>'2031-02-01T00:00:00Z',
        'show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[['id'=>wp_generate_uuid4(),'from'=>'2031-01-20','until'=>'2031-01-20','reason'=>'Ferie']]]);
    $base=['nonce'=>wp_create_nonce('rnl_letsreg_course'),'id'=>(string)$period,'version'=>'1','mode'=>'import'];
    $reject(static fn()=>Picker::dispatch($base+['operation'=>'save']),0);
    $reject(static fn()=>Picker::dispatch(array_replace($base,['nonce'=>'invalid','operation'=>'select','event_id'=>'12345'])),403);
    $assert($calls===0,'Invalid operation/nonce sent provider request');
    delete_option(Connection::OPTION);
    $search=Picker::dispatch($base+['operation'=>'search','query'=>'Salsa','offset'=>'0']);
    $assert($search['period']['end']==='2031-02-10' && $search['events'][0]['start_on']==='2031-01-06','Import search does not use selected period');
    $event=Picker::dispatch($base+['operation'=>'select','event_id'=>'12345']);
    $assert($event['suggestions']['fields']['start_time']==='18:30','Import does not reuse provider suggestions');
    $assert(($event['suggestions']['fields']['level_id'] ?? null)===$level,'Import does not suggest configured level');
    $raw=['level_id'=>(string)$event['suggestions']['fields']['level_id'],'title'=>'Salsa import test','weekday'=>'1','start_time'=>'18:30','end_time'=>'20:00','session_count'=>'5','room_id'=>(string)$room,
        'instructor_ids'=>[],'price_minor'=>'950,00','price_from'=>'1','price_basis'=>'person','price_terms'=>'Per deltaker','registration_status'=>'available',
        'registration_url'=>$event['event_url'],'registration_scope'=>'group','first_date'=>'2031-01-06','latest_date'=>'','timezone'=>'Europe/Oslo','currency'=>'NOK','breaks'=>[],
        'categories'=>[11=>['role'=>'leader','registration'=>'pair'],12=>['role'=>'follower','registration'=>'pair']]];
    $previewInput=$base+['operation'=>'preview_import','event_id'=>'12345','verification_id'=>$event['verification_id'],'course_id'=>(string)$course,'data'=>$raw];

    // New descriptions and multiple events are staged without creating any objects.
    $newInput=$previewInput;
    $newInput['description_mode']='new'; $newInput['course_id']='0'; $newInput['receipt']=$event['receipt'];
    $assert($event['description']===\RegiNor\Lite\Infrastructure\RichText::clean('<p>Lær &amp; dans</p><p>Ny linje<br>Videre</p>') ,'Provider safe paragraphs were not retained');
    $newInput['new_course']=['title'=>'New imported description','description'=>$event['description'],'dance_style'=>'Salsa','level_description'=>'Nybegynnere','partner_info'=>'Valgfri partner'];
    $descriptionCount=count($repo->listing('course')); $beforeNew=$repo->groups($period);
    $newPreview=Picker::dispatch($newInput); $newProposal=CourseActions::decode($newPreview['proposal']);
    $assert($newProposal['data']['course_id']===0 && str_contains($newPreview['html'],'Ny kursbeskrivelse'),'Pending description cannot be previewed');
    $assert(count($repo->listing('course'))===$descriptionCount && $repo->groups($period)===$beforeNew,'Description preview persisted objects');
    $badReceipt=$newInput; $badReceipt['receipt'].='x'; $reject(static fn()=>Picker::dispatch($badReceipt),403);
    $badDescription=$newInput; $badDescription['new_course']['dance_style']=''; $reject(static fn()=>Picker::dispatch($badDescription),0,['Dansestil mangler','Salsa eller Bachata']);
    $second=Picker::dispatch($base+['operation'=>'select','event_id'=>'12346']);
    $secondInput=$newInput; $secondInput['receipt']=$second['receipt']; $secondInput['event_id']='12346'; $secondInput['verification_id']=$second['verification_id'];
    $secondInput['data']['title']='Second import'; $secondInput['new_course']['title']='Second description';
    $secondPreview=Picker::dispatch($secondInput);
    $assert(Picker::dispatch($newInput)['proposal']!=='','Selecting a second event invalidated the first import receipt');
    $batch=$base+['operation'=>'confirm_import_batch','proposals'=>[$newPreview['proposal'],$secondPreview['proposal']]];
    $reject(static fn()=>Picker::dispatch(array_replace($batch,['proposals'=>array_fill(0,21,$newPreview['proposal'])])));
    $badBatch=$batch; $bad=CourseActions::decode($secondPreview['proposal']); $bad['data']['title']='Forged batch'; $badBatch['proposals'][1]=base64_encode(wp_json_encode($bad));
    $reject(static fn()=>Picker::dispatch($badBatch),403);
    $assert(count($repo->listing('course'))===$descriptionCount && $repo->groups($period)===$beforeNew,'Tampered second proposal left first course/description');
    $duplicates=$batch; $duplicates['proposals'][1]=$newPreview['proposal']; $reject(static fn()=>Picker::dispatch($duplicates),409);
    $assert(count($repo->listing('course'))===$descriptionCount && $repo->groups($period)===$beforeNew,'Duplicate inside batch leaves objects');
    $writes=0;
    $failSecond=static function($check,$objectId,$metaKey) use (&$writes) {
        if ($metaKey===ContentTypes::META && get_post_type($objectId)==='rnl_group' && ++$writes===2) return false;
        return $check;
    };
    add_filter('add_post_metadata',$failSecond,10,3); $reject(static fn()=>Picker::dispatch($batch)); remove_filter('add_post_metadata',$failSecond,10);
    $assert($writes===2 && count($repo->listing('course'))===$descriptionCount && $repo->groups($period)===$beforeNew,'Second storage failure leaves imported courses/descriptions');
    $sourceState=get_option(Connection::OPTION); $expiredSource=$sourceState; $expiredSource['verified_until']=time()-1; update_option(Connection::OPTION,$expiredSource,false);
    $reject(static fn()=>Picker::dispatch($batch),409); update_option(Connection::OPTION,$sourceState,false);
    putenv('RNL_LETSREG_PASSWORD=changed'); $reject(static fn()=>Picker::dispatch($batch),409); putenv('RNL_LETSREG_PASSWORD=synthetic-only');
    $beforeBatchCalls=$calls; $batchResult=Picker::dispatch($batch);
    $assert($batchResult['count']===2 && $calls===$beforeBatchCalls,'Batch count or no-network boundary incorrect');
    $imported=array_diff_key($repo->groups($period),$beforeNew);
    $assert(count($imported)===2 && count($repo->listing('course'))===$descriptionCount+2,'Batch did not create two descriptions and courses');
    foreach ($imported as $newId=>$item) {
        $created[]=$newId; $created[]=$item['data']['course_id'];
        $desc=$repo->get($item['data']['course_id'],'course');
        $assert(get_post_status($newId)==='draft' && $item['version']===1 && count($item['data']['sessions'])===5,'Bulk course incomplete or published');
        $assert($desc['data']['description']===$event['description'] && !str_contains($desc['data']['description'],'<script'),'Imported description is not reviewed plain text');
    }
    $reject(static fn()=>Picker::dispatch($batch),409);
    // Clear only these synthetic imports so the original single-import regression can continue.
    foreach ($imported as $newId=>$item) { wp_delete_post($newId,true); wp_delete_post($item['data']['course_id'],true); }
    $event=Picker::dispatch($base+['operation'=>'select','event_id'=>'12345']);
    $previewInput['verification_id']=$event['verification_id'];

    $beforeCalls=$calls; $before=$repo->groups($period);
    $preview=Picker::dispatch($previewInput); $proposal=CourseActions::decode($preview['proposal']);
    $assert(($proposal['data']['level_id'] ?? null)===$level,'Import preview loses suggested level');
    $assert($repo->groups($period)===$before,'Preview creates a course');
    $assert(count($proposal['data']['sessions'])===5 && !in_array('2031-01-20',array_column($proposal['data']['sessions'],'date')),'Import ignores shared break or session count');
    $assert($proposal['data']['sessions'][4]['date']==='2031-02-10' && str_contains($preview['html'],'Opprett kursutkast'),'Missing reviewable preview');
    $assert($calls===$beforeCalls,'Preview calls provider again');
    // A new import may record an already-started series; replanning must still preserve history.
    $clock=new class implements \RegiNor\Lite\Domain\Publication\Clock {
        public string $date='2031-01-14T12:00:00Z';
        public function now(): DateTimeImmutable { return new DateTimeImmutable($this->date); }
    };
    $historicalRepo=new CourseRepository($clock);
    $historicalPeriod=$created[]=$repo->create('period',array_replace($repo->get($period)['data'],['title'=>'Historical import test','visible_from'=>'2031-01-14T12:00:00Z']));
    $fields=array_diff_key($proposal['data'],array_flip(['period_id','course_id','period_version','sessions','letsreg_mapping']));
    $mapping=$proposal['data']['letsreg_mapping'];
    $introPeriod=$created[]=$repo->create('period',array_replace($repo->get($period)['data'],['title'=>'Early intro import test']));
    $introFields=array_replace($fields,['first_date'=>'2030-12-30','session_count'=>1]);
    $reject(static fn()=>$repo->previewImport($introPeriod,1,$course,$introFields,$mapping),0,['Tillat kursstart før kursperioden']);
    $introProposal=$repo->previewImport($introPeriod,1,$course,array_replace($introFields,['allow_early_start'=>true]),$mapping);
    $assert($introProposal['issues']===[] && array_column($introProposal['data']['sessions'],'date')===['2030-12-30'],'Approved early intro import lost date');
    $assert(str_contains(implode(' ',$introProposal['warnings']),'Bekreftet unntak'),'Early import does not explain override');
    $introGroup=$created[]=$repo->confirmImport($introProposal);
    $assert($repo->get($introGroup)['data']['allow_early_start']===true && $repo->get($introPeriod)['data']['start_date']==='2031-01-06','Intro import changes period or loses approval');
    $historical=$historicalRepo->previewImport($historicalPeriod,1,$course,$fields,$mapping);
    $assert($historical['issues']===[] && count($historical['data']['sessions'])===5,'Earlier dates block import or reduce total');
    $assert(array_column($historical['data']['sessions'],'date')===['2031-01-06','2031-01-13','2031-01-27','2031-02-03','2031-02-10'],'Import shifts start to today or ignores break');
    $information=implode(' ',$historical['warnings']);
    foreach (['totalt 5','2 passert','3 starter senere','første berørte dato: 2031-01-06','siste: 2031-01-13','14.01.2031 13:00','blokkerer ikke','Synlig fra'] as $detail) {
        $assert(str_contains($information,$detail),'Missing explanatory historical-import detail: '.$detail);
    }
    $review=CoursePage::importPreview($historical);
    $assert(str_contains($review,'data-confirm-import') && str_contains($review,'Til informasjon') && !str_contains($review,'data-import-issues'),'Historical import rendered as blocking error');
    $clock->date='2031-03-01T12:00:00Z';
    $ended=$historicalRepo->previewImport($historicalPeriod,1,$course,$fields,$mapping);
    $assert($ended['issues']===[] && str_contains(implode(' ',$ended['warnings']),'0 starter senere'),'Completed series cannot be recorded');
    $clock->date='2031-01-28T12:00:00Z'; // Another session starts between preview and confirmation.
    $historicalGroup=$created[]=$historicalRepo->confirmImport($historical);
    $history=$historicalRepo->get($historicalGroup);
    $assert($history['data']['sessions']===$historical['data']['sessions'] && get_post_status($historicalGroup)==='draft','Confirmation lost earlier dates or published');
    $reject(static fn()=>$historicalRepo->previewSessionChange($historicalGroup,1,$history['data']['sessions'][0]['id'],['date'=>'2031-02-04','status'=>'moved','reason'=>'Test']),0,['påbegynt eller avsluttet']);
    $replanned=$historicalRepo->previewGroup($historicalGroup,1,['start_time'=>'19:00','end_time'=>'20:30']);
    $assert($replanned['issues']===[] && array_slice($replanned['data']['sessions'],0,3)===array_slice($history['data']['sessions'],0,3),'Editing imported series rewrites history');
    $updated=$historicalRepo->confirm($replanned);
    $assert($updated['state']['data']['sessions'][3]['start_time']==='19:00','Future imported sessions cannot be changed');
    $assert($calls===$beforeCalls,'Historical import calls provider again');
    foreach (['room_id'=>'0','first_date'=>'2031-02-11','session_count'=>'7','start_time'=>'22:00'] as $key=>$value) {
        $invalid=$previewInput; $invalid['data'][$key]=$value;
        $details=match($key) { 'room_id'=>['Sal mangler','Undervisning'], 'session_count'=>['7 undervisningskvelder','bare 5','2031-02-10','Reduser antallet'], 'start_time'=>['20:00','22:00','samme dag'], default=>[] };
        $reject(static fn()=>Picker::dispatch($invalid),0,$details);
    }
    $assert($repo->groups($period)===$before,'Invalid preview leaves partial course');
    $confirm=$base+['operation'=>'confirm_import','proposal'=>$preview['proposal']];
    $tampered=$proposal; $tampered['data']['title']='Forged';
    $reject(static fn()=>Picker::dispatch(array_replace($confirm,['proposal'=>base64_encode(wp_json_encode($tampered))])),403);
    $state=get_option(Connection::OPTION); $expired=$state; $expired['verified_until']=time()-1; update_option(Connection::OPTION,$expired,false);
    $reject(static fn()=>Picker::dispatch($confirm),409); update_option(Connection::OPTION,$state,false);
    putenv('RNL_LETSREG_PASSWORD=changed'); $reject(static fn()=>Picker::dispatch($confirm),409); putenv('RNL_LETSREG_PASSWORD=synthetic-only');
    $reject(static fn()=>Picker::dispatch(array_replace($confirm,['id'=>(string)$room])));
    // Roll back even if post insertion succeeds but its metadata write fails.
    $fail=static fn($check,$objectId,$metaKey)=>$metaKey===ContentTypes::META && get_post_type($objectId)==='rnl_group' ? false : $check;
    add_filter('add_post_metadata',$fail,10,3); $reject(static fn()=>Picker::dispatch($confirm)); remove_filter('add_post_metadata',$fail,10);
    $assert($repo->groups($period)===$before,'Failed metadata write leaves an imported post');
    $p=$repo->get($period); $repo->update($period,$p['version'],array_replace($p['data'],['title'=>'Changed period']));
    $reject(static fn()=>Picker::dispatch($confirm),409);
    $previewInput['version']='2'; $base['version']='2'; $confirm['version']='2';
    $preview=Picker::dispatch($previewInput); $confirm['proposal']=$preview['proposal'];
    // Resource change invalidates preview; fields remain available for a fresh review.
    $r=$repo->get($room); $repo->update($room,$r['version'],array_replace($r['data'],['title'=>'Updated import room']));
    $reject(static fn()=>Picker::dispatch($confirm),409);
    $preview=Picker::dispatch($previewInput); $confirm['proposal']=$preview['proposal'];
    // A different new course may introduce a collision without changing the period version.
    $collision=$created[]=$repo->createGroup($period,$course,['weekday'=>1,'start_time'=>'18:30','end_time'=>'20:00','first_date'=>'2031-01-06']);
    $repo->confirm($repo->previewGroup($collision,1,[]));
    $collisionPreview=Picker::dispatch($previewInput); $assert(CourseActions::decode($collisionPreview['proposal'])['conflicts']!==[],'Import preview misses collisions with existing courses');
    $result=Picker::dispatch($confirm);
    parse_str(parse_url($result['redirect'],PHP_URL_QUERY),$query); $group=$created[]=(int)$query['group'];
    $saved=$repo->get($group);
    $assert(get_post_status($group)==='draft' && get_post_status($period)==='draft','Import publishes courses or period');
    $assert($saved['version']===1 && $saved['data']['letsreg_mapping']['event_id']===12345 && count($saved['data']['sessions'])===5,'Import did not atomically save schedule and mapping');
    $assert($saved['data']['price_minor']===95000 && $saved['data']['price_basis']==='person','Import doubles pair price');
    $assert($saved['data']['registration_url']===$event['event_url'] && $saved['data']['course_id']===$course && $saved['data']['room_id']===$room,'Import loses URL or manual local mapping');
    $assert($calls===$beforeCalls,'Import confirmation sends provider requests');
    $reject(static fn()=>Picker::dispatch($confirm),409);
    $reject(static fn()=>Picker::dispatch($previewInput),409);
    $assert(count($repo->groups($period))===2,'Repeated import creates duplicate');
    $repo->groupLifecycle($group,$saved['version'],$repo->get($period)['version'],false);
    $reject(static fn()=>Picker::dispatch($previewInput),409);
    $_GET=['page'=>'reginor-lite','period'=>$period,'step'=>'courses','import'=>'1']; ob_start(); CoursePage::render(); $html=ob_get_clean();
    $assert(str_contains($html,'data-mode="import"') && str_contains($html,'data-import-preview') && str_contains($html,'name="course_id"') && !str_contains($html,'notice-error'),'Import route/form does not render');
    $manager=$users[]=wp_insert_user(['user_login'=>'rnl_import_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'rnl_course_manager']);
    wp_set_current_user($manager);
    $assert(Mapping::canImport() && current_user_can('rnl_import_letsreg'), 'Manager lacks import capability');
    $reject(static fn()=>Connection::inspect(),403); $reject(static fn()=>Connection::check(),403);
    $reject(static fn()=>$repo->create('course',['title'=>'Not an import']),403);
    $reject(static fn()=>$repo->get($course,'course'),403);
    $reject(static fn()=>$repo->get($room,'room'),403);
    $delegatedPeriod=$created[]=$repo->create('period',array_replace($repo->get($period)['data'],['title'=>'Delegated import period']));
    $delegatedBase=['nonce'=>wp_create_nonce('rnl_letsreg_course'),'id'=>(string)$delegatedPeriod,'version'=>'1','mode'=>'import'];
    $_GET=['page'=>'reginor-lite','period'=>$delegatedPeriod,'step'=>'courses']; ob_start(); CoursePage::render(); $html=ob_get_clean();
    $assert(str_contains($html,'Hent nytt kurs fra LetsReg'),'Manager lacks import action');
    $_GET['import']='1'; ob_start(); CoursePage::render(); $html=ob_get_clean();
    $assert(str_contains($html,'data-mode="import"') && str_contains($html,'data-new-description') && !str_contains($html,'notice-error'),'Manager import form incomplete');
    delete_option(Connection::OPTION);
    $proposals=[];
    foreach ([12345,12346] as $eventId) {
        $selected=Picker::dispatch($delegatedBase+['operation'=>'select','event_id'=>(string)$eventId]);
        $input=array_replace($newInput,$delegatedBase,['event_id'=>(string)$eventId,'receipt'=>$selected['receipt'],'verification_id'=>$selected['verification_id']]);
        $input['data']['title']='Delegated '.$eventId;
        $input['description_choice']='separate'; // Existing legacy description differs; explicitly keep it intact.
        $proposals[]=Picker::dispatch($input)['proposal'];
    }
    $delegatedBatch=$delegatedBase+['operation'=>'confirm_import_batch','proposals'=>$proposals];
    $beforeCalls=$calls; $beforeDescriptions=count($repo->listing('course'));
    // Permissions must be rechecked at confirmation, not just when opening the editor.
    $managerUser=wp_get_current_user(); $managerUser->add_cap('rnl_import_letsreg',false);
    $reject(static fn()=>Picker::dispatch($delegatedBatch),403);
    $reject(static fn()=>$repo->confirmImport(CourseActions::decode($proposals[0])),403);
    $managerUser->remove_cap('rnl_import_letsreg');
    $denied=static fn($caps,$cap,$uid,$args)=>$cap==='edit_post' && ($args[0]??0)===$delegatedPeriod ? ['do_not_allow'] : $caps;
    add_filter('map_meta_cap',$denied,100,4);
    try { $reject(static fn()=>Picker::dispatch($delegatedBatch),403); }
    finally { remove_filter('map_meta_cap',$denied,100); }
    $reject(static fn()=>Picker::dispatch(array_replace($delegatedBatch,['nonce'=>'invalid'])),403);
    wp_set_current_user($admin);
    $reject(static fn()=>Picker::dispatch(array_replace($delegatedBatch,['nonce'=>wp_create_nonce('rnl_letsreg_course')])),403);
    wp_set_current_user($manager);
    // A failed second insert rolls back new/reused descriptions and both courses for delegated imports too.
    $writes=0; add_filter('add_post_metadata',$failSecond,10,3);
    try { $reject(static fn()=>Picker::dispatch($delegatedBatch)); }
    finally { remove_filter('add_post_metadata',$failSecond,10); }
    $assert($writes===2 && !$repo->groups($delegatedPeriod) && count($repo->listing('course'))===$beforeDescriptions,'Delegated bulk failure left partial imports');
    $result=Picker::dispatch($delegatedBatch);
    $assert($result['count']===2 && $calls===$beforeCalls,'Manager bulk import failed or sends provider calls during confirmation');
    foreach ($repo->groups($delegatedPeriod) as $newId=>$item) {
        $created[]=$newId; $created[]=$item['data']['course_id'];
        $assert(get_post_status($newId)==='draft' && count($item['data']['sessions'])===5,'Delegated course published or incomplete');
        $assert($repo->resource($item['data']['course_id'],'course')['description']===$event['description'],'Manager import lost description');
        $reject(static fn()=>$repo->get($item['data']['course_id'],'course'),403);
    }
    $reject(static fn()=>Picker::dispatch($delegatedBatch),409);
    // Single import can also reuse an existing shared description and room without editing them.
    $selected=Picker::dispatch($delegatedBase+['operation'=>'select','event_id'=>'12347']);
    $input=array_replace($previewInput,$delegatedBase,['event_id'=>'12347','receipt'=>$selected['receipt'],'verification_id'=>$selected['verification_id']]);
    $single=Picker::dispatch($input);
    $confirmation=$delegatedBase+['operation'=>'confirm_import','proposal'=>$single['proposal']];
    wp_set_current_user($admin);
    $r=$repo->get($room); $repo->update($room,$r['version'],array_replace($r['data'],['title'=>'Resource changed during delegated import']));
    wp_set_current_user($manager);
    $reject(static fn()=>Picker::dispatch($confirmation),409);
    $confirmation['proposal']=Picker::dispatch($input)['proposal'];
    $result=Picker::dispatch($confirmation);
    parse_str(parse_url($result['redirect'],PHP_URL_QUERY),$query); $newId=$created[]=(int)$query['group'];
    $assert($repo->get($newId)['data']['course_id']===$course,'Manager single import did not reuse selected description');
    wp_set_current_user(0);
    $reject(static fn()=>Picker::dispatch(array_replace($delegatedBase,['nonce'=>wp_create_nonce('rnl_letsreg_course'),'operation'=>'select','event_id'=>'12345'])),403);

} finally {
    if (isset($fail)) remove_filter('add_post_metadata',$fail,10);
    remove_filter('pre_http_request',$http,PHP_INT_MAX);
    if ($old===null) delete_option(Connection::OPTION); else update_option(Connection::OPTION,$old,false);
    foreach ($env as $name=>$value) putenv($value===false ? $name : $name.'='.$value);
    wp_set_current_user($admin);
    // Include any course whose creation succeeded before an assertion interrupted bookkeeping.
    if (isset($period)) foreach (get_posts(['post_type'=>'rnl_group','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids']) as $candidate) {
        $meta=get_post_meta($candidate,ContentTypes::META,true);
        if (in_array($meta['data']['period_id']??0,[$period,$historicalPeriod??0,$delegatedPeriod??0],true)) {
            $created[]=$candidate;
            if (($meta['data']['course_id']??0) && $meta['data']['course_id']!==$course) $created[]=$meta['data']['course_id'];
        }
    }
    foreach (array_reverse(array_unique($created)) as $id) wp_delete_post($id,true);
    require_once ABSPATH.'wp-admin/includes/user.php'; foreach ($users as $id) wp_delete_user($id);
    wp_set_current_user($oldUser); $_GET=$oldGet;
}
WP_CLI::success("$checks LetsReg import checks passed; synthetic data removed, no external requests sent.");
