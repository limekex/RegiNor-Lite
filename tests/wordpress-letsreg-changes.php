<?php
use RegiNor\Lite\Infrastructure\{CourseRepository,ContentTypes,LetsRegConnection as Connection,LetsRegMapping as Mapping,LetsRegChanges as Changes,ImportedDescriptions as Descriptions,LetsRegAvailabilityStore as Availability,Mutation};
use RegiNor\Lite\Admin\{LetsRegChangesPanel as Panel,CoursePage};
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local')throw new RuntimeException('Local only');
$created=[];$users=[];$checks=0;$calls=0;$env=[];$oldUser=get_current_user_id();$oldGet=$_GET;$failure=false;
$old=[];foreach([Connection::OPTION,'rnl_letsreg_poll','rnl_sales_history_enabled']as$key)$old[$key]=get_option($key,null);
foreach(['AFFILIATE_ID','ORGANIZER_ID','USERNAME','PASSWORD','CLIENT_ID']as$key){$name='RNL_LETSREG_'.$key;if(defined($name))throw new RuntimeException('Tests require environment variables');$env[$name]=getenv($name);}
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];$repo=new CourseRepository();
$assert=static function($ok,$why)use(&$checks){++$checks;if(!$ok)throw new RuntimeException($why);};
$reject=static function($fn,$code=0)use($assert){try{$fn();}catch(Throwable $e){$assert(!$code||$e->getCode()===$code,'Wrong error: '.$e->getMessage());return;}$assert(false,'Invalid operation accepted');};
$text='Lær salsa fra bunnen av. Vi øver på grunntrinn, rytme, samspill og enkle kombinasjoner i et hyggelig miljø med god tid til spørsmål og repetisjon.';
$event=['id'=>12345,'organizer'=>['id'=>42,'affiliateId'=>7],'name'=>'Salsa nybegynner','description'=>$text,'active'=>true,'published'=>true,'isCancelled'=>false,'lastUpdate'=>'2026-09-21T12:00:00Z','eventUrl'=>'https://www.letsreg.com/event/changes','startDate'=>'2031-01-06T18:00:00+01:00','endDate'=>'2031-01-13T19:00:00+01:00'];
$http=static function($pre,$args,$url)use(&$calls,&$event,&$failure){++$calls;if(Mutation::active())throw new RuntimeException('Network under repository lock');
 if($failure && str_contains($url,'/events/'))return ['response'=>['code'=>503],'headers'=>[],'body'=>''];
 $data=match($url){Connection::TOKEN_URL=>['access_token'=>'synthetic.changes.token','token_type'=>'bearer','expires_in'=>3600],Connection::ORIGIN.'/organizers/42'=>['id'=>42,'affiliateId'=>7,'name'=>'Synthetic'],Connection::ORIGIN.'/events/12345'=>$event,Connection::ORIGIN.'/events/12346'=>array_replace($event,['id'=>12346]),Connection::ORIGIN.'/events/12345/prices',Connection::ORIGIN.'/events/12346/prices'=>[['id'=>11,'name'=>'Fører','active'=>true,'price'=>100]],default=>throw new RuntimeException('Real request blocked')};
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
 $p2=$period();$proposal=$repo->previewImport($p2,1,0,$fields,$mapping,$description);
 $assert($proposal['description_plan']['action']==='reuse','Repeated import creates duplicate description');
 $g2=$created[]=$repo->confirmImport($proposal);$assert($repo->get($g2)['data']['course_id']===$c1 && $repo->get($c1)['version']===1,'Reuse rewrites or duplicates resource');
 $p3=$period();$proposal=$repo->previewImport($p3,1,0,$fields,$mapping,$description,'','separate');$assert($proposal['description_plan']['action']==='reuse','Identical explicit separate makes duplicate');
 $mapping2=$select(12346);$proposal=$repo->previewImport($p3,1,0,$fields,$mapping2,$description);$assert($proposal['description_plan']['action']==='reuse','Identical content across events not reused');
 $g3=$created[]=$repo->confirmImport($proposal);$assert($repo->get($g3)['data']['course_id']===$c1,'Cross-event reuse mismatch');
 $mapping=$select();$p4=$period();$changedDescription=array_replace($description,['description'=>$text.' Velkommen!']);
 $proposal=$repo->previewImport($p4,1,0,$fields,$mapping,$changedDescription);
 $assert($proposal['description_plan']['action']==='update' && count($proposal['description_plan']['references'])===3,'Small change not proposed as shared update');
 $html=CoursePage::importPreview($proposal);$assert(str_contains($html,'Lokalt nå')&&str_contains($html,'3 lokale kurs'),'Review does not explain shared changes');
 $g4=$created[]=$repo->confirmImport($proposal);$assert($repo->get($g4)['data']['course_id']===$c1&&$repo->get($c1)['version']===2,'Small approved update did not retain resource ID');
 // Major change requires a choice; selecting update stays on the same ID after explicit review.
 $p5=$period();$major=array_replace($changedDescription,['description'=>'Et helt annet intensivkurs med nye krav.']);
 $proposal=$repo->previewImport($p5,1,0,$fields,$mapping,$major);$assert($proposal['issues']!==[] && $proposal['description_plan']['reason']==='major','Major change silently merged');$reject(static fn()=>$repo->confirmImport($proposal));
 $proposal=$repo->previewImport($p5,1,0,$fields,$mapping,$major,'','update');$assert($proposal['description_plan']['action']==='update','Major update cannot be selected');
 // Do not commit this alternative. Separate choice creates one new description, leaving shared original intact.
 $proposal=$repo->previewImport($p5,1,0,$fields,$mapping,$major,'','separate');$g5=$created[]=$repo->confirmImport($proposal);$c5=$created[]=$repo->get($g5)['data']['course_id'];
 $assert($c5!==$c1 && $repo->get($c1)['data']['description']===$changedDescription['description'],'Separate choice overwrites shared text');
 // Local edits block source replacement.
 $local=$repo->get($c5);$repo->update($c5,$local['version'],array_replace($local['data'],['description'=>'Egen lokal tekst']));
 $p6=$period();$proposal=$repo->previewImport($p6,1,0,$fields,$mapping,array_replace($major,['description'=>'Ny kilde tekst']),'','update');
 $assert($proposal['issues']!==[],'Local edits or ambiguous same-event descriptions not protected');
 // Source changes are observed without mutating local content, including manually controlled courses.
 $event['description']=$text.' Ny informasjon.';$event['name']='Salsa nybegynner oppdatert';$event['lastUpdate']='2026-09-21T13:00:00Z';
 Availability::failure(Connection::identity(),12345);delete_option('rnl_letsreg_poll');$before=$calls;Availability::runDue([$g1]);
 $group=$repo->get($g1);$review=Changes::inspect($g1,$group['data']);
 $assert($calls>$before&&$review['state']==='changed'&&isset($review['changes']['description'],$review['changes']['lastUpdate']),'Background source change not detected');
 $assert($repo->get($c1)['data']['description']===$changedDescription['description'],'Polling overwrites local text');
 ob_start();Panel::render($g1,$group);$html=ob_get_clean();$assert(str_contains($html,'Sist gjennomgått')&&str_contains($html,'Uten mal oppdateres bare kursbeskrivelsen'),'Source review missing');
 ob_start();Panel::badge($g1,$group['data']);$badge=ob_get_clean();$assert(str_contains($badge,'Endret hos LetsReg'),'Period warning missing');
 $payload=['_wpnonce'=>wp_create_nonce('rnl_letsreg_review'),'id'=>(string)$g1,'version'=>(string)$group['version'],'description_version'=>(string)$repo->get($c1)['version'],'source_hash'=>Changes::hash($review['latest']['snapshot']),'operation'=>'description'];
 $before=$calls;$reject(static fn()=>Panel::dispatch(array_replace($payload,['_wpnonce'=>'wrong'])),403);$reject(static fn()=>Panel::dispatch(array_replace($payload,['source_hash'=>'wrong'])),409);$assert($calls===$before,'Invalid review calls API');
 wp_update_post(['ID'=>$g1,'post_status'=>'publish']);wp_update_post(['ID'=>$p1,'post_status'=>'publish']);$periodBefore=$repo->get($p1);Panel::dispatch($payload);
 $assert(get_post_status($p1)==='publish' && $repo->get($p1)===$periodBefore,'Source review changed published period');
 $assert(get_post_status($g1)==='publish'&&$repo->get($g1)['data']===$group['data'],'Description update changed publication or schedule');
 $assert($repo->get($c1)['data']['description']===$event['description'],'Approved source description not updated');
 $review=Changes::inspect($g1,$group['data']);$assert(isset($review['changes']['name'])&&!isset($review['changes']['description']),'Description approval dismissed unrelated changes');
 $payload['operation']='keep';$payload['description_version']=(string)$repo->get($c1)['version'];Panel::dispatch($payload);
 $assert(Changes::inspect($g1,$group['data'])['state']==='unchanged','Keep local did not acknowledge source');
 // Revenue/participant counters are not editorial changes, even on a fresh control.
 $event['registeredParticipants']=12;$event['ordersTotalSum']=1200;$select();$assert(Changes::inspect($g1,$group['data'])['state']==='unchanged','Sales counters trigger editorial alert');
 $failure=true;delete_option(Connection::OPTION);Connection::checkCourse(12345);$assert(!Changes::inspect($g1,$group['data'])['fresh'],'Failed check offers stale update');$reject(static fn()=>Panel::dispatch($payload),409);$failure=false;$select();
 // Manager can update a proven imported resource but never bypass local-edit protection.
 $manager=$users[]=wp_insert_user(['user_login'=>'rnl_changes_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'rnl_course_manager']);
 wp_set_current_user($manager);$event['description'].=' Ekstra tekst.';$select();
 $review=Changes::inspect($g1,$repo->get($g1)['data']);$payload['_wpnonce']=wp_create_nonce('rnl_letsreg_review');$payload['source_hash']=Changes::hash($review['latest']['snapshot']);$payload['operation']='description';
 Panel::dispatch($payload);$assert($repo->resource($c1,'course')['description']===$event['description'],'Manager cannot approve imported description');
 $review=Changes::inspect($g5,$repo->get($g5)['data']);$localPayload=array_replace($payload,['id'=>(string)$g5,'version'=>(string)$repo->get($g5)['version'],'description_version'=>(string)get_post_meta($c5,ContentTypes::META,true)['version']]);
 $reject(static fn()=>Panel::dispatch($localPayload),409);
 wp_set_current_user(0);$reject(static fn()=>Panel::dispatch($payload),403);wp_set_current_user($admin);
 // Published course exposes text editing and source review; manual text edits preserve both lifecycles.
 $groupBefore=$repo->get($g1);$courseBefore=$repo->get($c1);
 ob_start();(new ReflectionMethod(CoursePage::class,'group'))->invoke(new CoursePage(),$g1,$groupBefore);$publishedHtml=ob_get_clean();
 $assert(str_contains($publishedHtml,'rnl-source-review') && str_contains($publishedHtml,'name="data[description]"') && !str_contains($publishedHtml,'Ta perioden tilbake til kladd før du endrer kurset.'),'Published text editor unavailable or misleading');
 $repo->saveCourseTexts($g1,$groupBefore['version'],$courseBefore['version'],['description'=>'<p>Manuelt godkjent tekst.</p>','partner_info'=>'Ingen partner nødvendig.']);
 $assert($repo->get($g1)===$groupBefore && $repo->get($p1)===$periodBefore && get_post_status($g1)==='publish' && get_post_status($p1)==='publish','Text edit changed schedule or publication');
 $assert($repo->get($c1)['data']['description']==='<p>Manuelt godkjent tekst.</p>','Published text not saved');
 $reject(static fn()=>$repo->saveCourseTexts($g1,$groupBefore['version'],$courseBefore['version'],['description'=>'Stale write']),409);
 $reject(static fn()=>$repo->saveCourseTexts($g1,$groupBefore['version'],$repo->get($c1)['version'],['description'=>'']));
 $reject(static fn()=>$repo->saveCourseTexts($g1,$groupBefore['version'],$repo->get($c1)['version'],['title'=>'Forbidden']));
 // An older linked description without provenance can be reused exactly, never overwritten automatically.
 delete_post_meta($c5,Descriptions::META);$existing=$repo->get($c5)['data'];$plan=Descriptions::plan($mapping,$existing);$assert($plan['action']==='reuse'&&$plan['id']===$c5,'Legacy exact description not reused');
 // Conflict detection against versions/reference changes at confirmation.
 $p7=$period();$mapping=$select(12346);$incoming=$repo->get($c1)['data'];$proposal=$repo->previewImport($p7,1,0,$fields,$mapping,$incoming);
 $local=$repo->get($c1);$repo->update($c1,$local['version'],array_replace($local['data'],['description'=>'Concurrent edit']));$reject(static fn()=>$repo->confirmImport($proposal),409);
 $assert($repo->groups($p7)===[],'Concurrent resource edit leaves partial import');
 // Structured descriptions: real provider HTML, picker suggestions, persistence and later review.
 $event['description']='<p><strong>Kursbeskrivelse</strong></p><p>Dans i ring.</p><p><b>Dansestil:</b></p><p>Rueda</p><p>Nivå og forkunnskaper</p><p>Nybegynnerkurs kreves.</p><p>Partnerinformasjon</p><p>Par kan melde seg sammen.</p><p>Prisvilkår og tillegg</p><p>Studentrabatt 100 kr.</p><p>Praktisk informasjon</p><p>Bare hos LetsReg.</p><script>unsafe()</script>';
 $event['lastUpdate']='2026-09-22T12:00:00Z';delete_option(Connection::OPTION);
 $p8=$period();
 $selection=\RegiNor\Lite\Admin\LetsRegCoursePicker::dispatch(['nonce'=>wp_create_nonce('rnl_letsreg_course'),'id'=>(string)$p8,'mode'=>'import','operation'=>'select','event_id'=>'12345']);
 $parsed=$selection['description_template'];
 $assert($parsed['mode']==='structured' && $parsed['fields']['dance_style']==='Rueda' && !str_contains(implode(' ',$parsed['fields']),'Bare hos'),'HTML or bold/plain headings not parsed safely');
 $assert($selection['suggestions']['fields']['price_terms']==='<p>Studentrabatt 100 kr.</p>','Price terms omitted from course suggestions');
 $source=Mapping::source();$mapping=Mapping::build(12345,$source['verification_id'],[11=>['role'=>'leader','registration'=>'single']]);
 $incoming=array_replace($description,\RegiNor\Lite\Domain\LetsRegDescriptionTemplate::courseFields($parsed),['title'=>'Rueda mal']);
 $proposal=$repo->previewImport($p8,1,0,array_replace($fields,['price_terms'=>$parsed['fields']['price_terms']]),$mapping,$incoming,'','separate');
 $g8=$created[]=$repo->confirmImport($proposal);$c8=$created[]=$repo->get($g8)['data']['course_id'];
 $assert($repo->get($c8)['data']['description']==='<p>Dans i ring.</p>' && $repo->get($g8)['data']['price_terms']==='<p>Studentrabatt 100 kr.</p>','Template import stores full prose instead of reviewed fields');
 $event['description']=str_replace(['Nybegynnerkurs kreves.','Par kan melde seg sammen.'],['Nybegynner og Rueda 1 kreves.','Ingen partner nødvendig.'],$event['description']);$mapping=$select();
 $group=$repo->get($g8);$review=Changes::inspect($g8,$group['data']);
 ob_start();Panel::render($g8,$group);$templateHtml=ob_get_clean();
 $assert(str_contains($templateHtml,'Nybegynner og Rueda 1 kreves.') && str_contains($templateHtml,'Etter godkjenning'),'Template review omits split field differences');
 $repo->reviewLetsRegSource($g8,$group['version'],$repo->get($c8)['version'],Changes::hash($review['latest']['snapshot']),'description');
 $assert($repo->get($c8)['data']['level_description']==='<p>Nybegynner og Rueda 1 kreves.</p>' && $repo->get($c8)['data']['partner_info']==='<p>Ingen partner nødvendig.</p>','Source review does not update split fields');
 $assert($repo->get($g8)['data']===$group['data'],'Template review mutates group price terms or schedule');
 $event['description']=str_replace('Partnerinformasjon','Parnerinformasjon',$event['description']);$mapping=$select();$review=Changes::inspect($g8,$group['data']);
 $reject(static fn()=>$repo->reviewLetsRegSource($g8,$group['version'],$repo->get($c8)['version'],Changes::hash($review['latest']['snapshot']),'description'),409);

 // Full API -> template -> confirmed import -> catalog -> rendered sections, with links and paragraphs.
 $rich = '<p>Første <strong>avsnitt</strong>.</p><p>Andre <em>avsnitt</em> med <a href="https://example.org/medlem?x=1&amp;y=2">medlemskap</a>.</p><ul><li>Rabatt</li></ul>';
 $event['description'] = '';
 foreach (\RegiNor\Lite\Domain\LetsRegDescriptionTemplate::HEADINGS as $heading=>$key) {
     $event['description'] .= '<p><strong>'.$heading.'</strong></p>'.($key==='dance_style'?'<p>Salsa</p>':($key==='ignored'?'<p>IGNORED-CONTEXT</p>':$rich));
 }
 $mapping=$select();$p9=$period();$richRoom=$created[]=$repo->create('room',['title'=>'Rich text room','venue_id'=>$venue]);$parsed=\RegiNor\Lite\Domain\LetsRegDescriptionTemplate::parse(Mapping::source()['event']['description'] ?? $event['description']);
 $incoming=array_replace($description,\RegiNor\Lite\Domain\LetsRegDescriptionTemplate::courseFields($parsed),['title'=>'Rik tekst']);
 $proposal=$repo->previewImport($p9,1,0,array_replace($fields,['room_id'=>$richRoom,'price_terms'=>$parsed['fields']['price_terms']]),$mapping,$incoming,'','separate');
 $g9=$created[]=$repo->confirmImport($proposal);$c9=$created[]=$repo->get($g9)['data']['course_id'];
 $repo->publish($repo->previewPublication($p9,1,true));
 $clock=new class implements \RegiNor\Lite\Domain\Publication\Clock { public function now():DateTimeImmutable{return new DateTimeImmutable('2031-01-01T12:00:00Z');} };
 $render=static function()use($clock,$g9){return (new \RegiNor\Lite\Frontend\Renderer())->render((new \RegiNor\Lite\Frontend\Catalog($clock))->read(),['rnl_course'=>$g9]);};
 $html=$render();
 foreach(['description','level','partner','price-terms']as$section){
     preg_match('/<section class="[^"]*rnl-section-'.preg_quote($section,'/').'[^"]*">(.*?)<\/section>/s',$html,$match);
     $assert(isset($match[1])&&substr_count($match[1],'<p>')===2,'Paragraphs missing in '.$section);
     $assert(str_contains($match[1],'<a href="https://example.org/medlem?x=1&amp;y=2" target="_blank" rel="noopener noreferrer">'),'Link lost in '.$section);
     $assert(str_contains($match[1],'<strong>avsnitt</strong>')&&str_contains($match[1],'<em>avsnitt</em>')&&str_contains($match[1],'<li>Rabatt</li>'),'Formatting lost in '.$section);
 }
 $assert(!str_contains($html,'IGNORED-CONTEXT'),'Ignored section exposed');
 // Repair historical plain terms even when provider snapshot was already acknowledged.
 $group=$repo->get($g9);$originalGroup=$group;$periodBefore=$repo->get($p9);
 $repo->saveCourseTexts($g9,$group['version'],$repo->get($c9)['version'],['price_terms'=>'Gammel lokal tekst uten lenker']);
 $group=$repo->get($g9);$review=Changes::inspect($g9,$group['data']);
 $assert($review['state']==='unchanged','Local text edit changes provider baseline');
 ob_start();Panel::render($g9,$group);$panel=ob_get_clean();
 $assert(str_contains($panel,'Godkjenn prisvilkår for dette kurset')&&str_contains($panel,'Gammel lokal tekst uten lenker')&&str_contains($panel,'https://example.org/medlem'),'Unchanged provider hides price terms repair/diff');
 $payload=['_wpnonce'=>wp_create_nonce('rnl_letsreg_review'),'id'=>(string)$g9,'version'=>(string)$group['version'],'description_version'=>(string)$repo->get($c9)['version'],'source_hash'=>Changes::hash($review['latest']['snapshot']),'operation'=>'price_terms'];
 wp_set_current_user($manager);$payload['_wpnonce']=wp_create_nonce('rnl_letsreg_review');Panel::dispatch($payload);
 $assert($repo->resource($c9,'course')['description']===\RegiNor\Lite\Infrastructure\RichText::clean($parsed['fields']['description']),'Terms approval changes shared description');
 $updated=$repo->get($g9);$assert($updated['data']===$originalGroup['data']&&get_post_status($g9)==='publish'&&$repo->get($p9)===$periodBefore,'Terms repair changed price, schedule, period or publication');
 $reject(static fn()=>Panel::dispatch($payload),409);
 wp_set_current_user($admin);
 $assert(str_contains($render(),'target="_blank" rel="noopener noreferrer">medlemskap</a>'),'Approved terms lost in output');

 $group=$repo->get($g9);$course=$repo->get($c9);
 $payload=['_wpnonce'=>wp_create_nonce('rnl_course_command'),'command'=>'save_course_description','id'=>(string)$g9,'version'=>(string)$group['version'],'course_version'=>(string)$course['version'],
     'data'=>array_intersect_key($course['data'],array_flip(['description','level_description','dance_style','partner_info']))+['price_terms'=>$rich.'<script>badTerms()</script>']];
 (new \RegiNor\Lite\Admin\CourseActions($repo))->handle($payload);
 $stored=$repo->get($g9);
 $assert(!str_contains($stored['data']['price_terms'],'badTerms')&&str_contains($stored['data']['price_terms'],'<a href='),'Backend text save strips links or retains unsafe terms');
 $assert(get_post_status($g9)==='publish'&&$repo->get($p9)===$periodBefore,'Backend text save unpublishes course');
}finally{
 remove_filter('pre_http_request',$http,PHP_INT_MAX);wp_set_current_user($admin);
 foreach(array_reverse(array_unique($created))as$id)wp_delete_post($id,true);
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$id)if(!is_wp_error($id))wp_delete_user($id);
 foreach($old as$key=>$value){if($value===null)delete_option($key);else update_option($key,$value);}
 foreach($env as$key=>$value)putenv($value===false?$key:$key.'='.$value);
 wp_set_current_user($oldUser);$_GET=$oldGet;
}
WP_CLI::success("$checks source change and description reuse checks passed; synthetic data removed.");
