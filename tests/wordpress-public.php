<?php
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\Renderer;
use RegiNor\Lite\Frontend\SchemaPresenter;
use RegiNor\Lite\Frontend\PublicSite;
use RegiNor\Lite\Frontend\CourseSitemap;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$created=[]; $checks=0; $original=get_current_user_id(); $oldPage=get_option('rnl_course_page_id', null);
$assert=static function(bool $ok,string $message) use (&$checks):void { ++$checks; if(!$ok) throw new RuntimeException($message); };
$clock=new class implements Clock { public string $time='2029-12-01T12:00:00Z'; public function now():DateTimeImmutable{return new DateTimeImmutable($this->time);} };
$repo=new CourseRepository($clock); $catalog=new Catalog($clock);
$admin=(int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0];
$types=static fn()=>['rnl_public_profile'];
try {
wp_set_current_user($admin); register_post_type('rnl_public_profile',['public'=>true]); add_filter('rnl_instructor_post_types',$types);
$venue=$created[]=$repo->create('venue',['title'=>'Offentlig teststed','address'=>'Testgata 12']);
$room=$created[]=$repo->create('room',['title'=>'Sal 1','venue_id'=>$venue]);
$teacher=$created[]=wp_insert_post(['post_type'=>'rnl_public_profile','post_status'=>'publish','post_title'=>'Instruktørnavn']);
add_post_meta($teacher,'private_note','INTERNAL-PROFILE-SECRET');
$course=$created[]=$repo->create('course',['title'=>'Salsa nybegynner','description'=>'Lær grunntrinnene sammen.','level_description'=>'For deg som ikke har danset før.','dance_style'=>'Salsa','partner_info'=>'Du kan komme alene.','audience'=>'beginner']);
$page=$created[]=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Kurs test','post_content'=>'[reginor_courses]']); update_option('rnl_course_page_id',$page);
$p=['title'=>'Vårkurs','timezone'=>'Europe/Oslo','start_date'=>'2030-01-07','default_session_count'=>3,'default_room_id'=>$room,'default_price_minor'=>123450,'default_price_basis'=>'person',
'visible_from'=>'2030-01-01T10:00:00Z','visible_until'=>'2030-03-01T10:00:00Z','sales_from'=>'2030-01-02T10:00:00Z','sales_until'=>'2030-01-06T10:00:00Z','show_as_upcoming'=>true,'cancelled'=>false,'breaks'=>[]];
$period=$created[]=$repo->create('period',$p);
$group=$created[]=$repo->createGroup($period,$course,['weekday'=>1,'start_time'=>'18:00','end_time'=>'19:00','instructor_ids'=>[$teacher],'registration_status'=>'available','registration_url'=>'https://www.letsreg.com/event/test','price_terms'=>'Ingen tillegg.']);
$repo->confirm($repo->previewGroup($group,1,[]));
$repo->publish($repo->previewPublication($period,1,true));
wp_set_current_user(0);
$assert($catalog->read()['groups']===[],'Kurs før vinduet er synlig.');
$clock->time='2030-01-01T09:59:59Z'; $assert($catalog->read()['groups']===[],'Ett sekund før start eksponeres.');
$clock->time='2030-01-01T10:00:00Z'; $read=$catalog->read(); $assert(isset($read['groups'][$group]),'Kurs mangler ved eksakt start.');
$assert($read['default']===$period && $read['upcoming']===[$period],'Kommende periode velges ikke.');
$g=$read['groups'][$group]; $assert($g['status']==='later' && $g['registration_url']==='','Salget åpner før eget vindu.');
$encoded=wp_json_encode($read); foreach(['history','actor_id','period_version','INTERNAL-PROFILE-SECRET','rnl_state'] as $secret) {$assert(!str_contains($encoded,$secret),'Offentlig modell lekker '.$secret);}
$clock->time='2030-01-02T10:00:00Z'; $read=$catalog->read(); $g=$read['groups'][$group];
$assert($g['status']==='available' && $g['registration_url']!=='','Salg åpner ikke ved eksakt start.');
$assert($read['expires_at']===strtotime('2030-01-06T10:00:00Z'),'Neste statusgrense er feil.');
$html=(new Renderer())->render($read,[]); $assert(str_contains($html,'Alle nivåer') && str_contains($html,'Testgata 12') && str_contains($html,number_format_i18n(1234.5, 2)),'Kurskort mangler nivåinngang, adresse eller eksakt pris.');
$assert(str_contains($html,'application/ld+json'),'Oversikten mangler strukturerte data.');
// Imported rich text survives storage and rendering, while active content does not.
$rich = '<p>Dans <strong>sammen</strong> og <em>rolig</em>.</p><ul><li>Grunntrinn</li></ul><p><a href="https://example.org/info?x=1&amp;y=2" onclick="evil()" style="color:red" target="_self">Les mer</a><a href="javascript:evil()">Utrygg</a></p><script>evil()</script><img src="https://example.org/pixel">';
$clean = \RegiNor\Lite\Infrastructure\RichText::clean($rich);
$assert(str_contains($clean, '<strong>sammen</strong>') && str_contains($clean, '<li>Grunntrinn</li>') && str_contains($clean, 'target="_blank" rel="noopener noreferrer"'), 'Rich import strips supported formatting or link protection');
$assert(!str_contains($clean, 'evil') && !str_contains($clean, '<img') && !str_contains($clean, 'style='), 'Unsafe provider content survives sanitization');
$richRead = $read; $richRead['groups'][$group]['description'] = $rich;
$richHtml = (new Renderer())->render($richRead, ['rnl_course'=>$group]);
$assert(str_contains($richHtml, '<strong>sammen</strong>') && !str_contains($richHtml, 'evil'), 'Public detail escapes rich content or exposes active markup');
$richSchema = SchemaPresenter::group($richRead['groups'][$group], 'https://example.org/course');
$assert(!str_contains($richSchema['description'], '<') && str_contains($richSchema['description'], 'Dans sammen'), 'Schema includes raw provider HTML');
$assert(\RegiNor\Lite\Infrastructure\RichText::clean($clean) === $clean, 'Rich sanitizer changes content on each save');
$translation = static fn($value,$context,$name) => str_ends_with($name,'.description') ? '<p><em>Translated</em><script>badTranslation()</script></p>' : $value;
add_filter('wpml_translate_single_string',$translation,10,3);
$translated = \RegiNor\Lite\Infrastructure\Wpml::data($course,'course',['description'=>'Original']);
remove_filter('wpml_translate_single_string',$translation,10);
$assert(str_contains($translated['description'],'<em>Translated</em>')&&!str_contains($translated['description'],'badTranslation'),'Translated rich text lost or unsafe');
$noCacheReason = ''; $cacheHook = static function($reason) use (&$noCacheReason) { $noCacheReason = $reason; };
add_action('litespeed_control_set_nocache', $cacheHook); PublicSite::noCache(); remove_action('litespeed_control_set_nocache', $cacheHook);
$assert($noCacheReason !== '', 'LiteSpeed no-cache hook not fired');

// Both overview variants preserve the entire destination and the analytics hook.
$linkedRead = $read;
$destination = 'https://www.letsreg.com/event/test?utm_source=google&utm_campaign=host&_gl=synthetic-linker&ref=course%2F1#registration';
$linkedRead['groups'][$group]['registration_url'] = $destination;
foreach (['list', 'week'] as $view) {
    $linksHtml = (new Renderer())->render($linkedRead, ['rnl_view' => $view]);
    $tags = new WP_HTML_Tag_Processor($linksHtml); $found = 0; $internal = 0;
    while ($tags->next_tag('a')) {
        if ($tags->get_attribute('data-rnl-letsreg') === (string) $group) {
            ++$found;
            $assert($tags->get_attribute('href') === $destination, 'Registration URL lost parameters or fragment: ' . $view);
            $assert($tags->get_attribute('target') === '_blank' && $tags->get_attribute('rel') === 'noopener', 'Registration must open safely without suppressing referral: ' . $view);
            $assert(str_contains($tags->get_attribute('aria-label'), 'ny fane') && str_contains($tags->get_attribute('aria-label'), $g['title']), 'Registration label omits destination/course: ' . $view);
        } elseif (str_contains($tags->get_attribute('aria-label') ?? '', 'Se kurset:')) { ++$internal; }
    }
    $assert($found === 1 && $internal === 1 && str_contains($linksHtml, 'Meld meg på'), 'Missing separate read/registration actions: ' . $view);
    foreach (['closed', 'later', 'full', 'cancelled', 'ended', 'unknown'] as $status) {
        $blocked = $linkedRead; $blocked['groups'][$group]['status'] = $status;
        $blockedHtml = (new Renderer())->render($blocked, ['rnl_view' => $view]);
        $assert(!str_contains($blockedHtml, 'data-rnl-letsreg=') && str_contains($blockedHtml, 'Se kurset'), 'Unavailable course permits direct signup: ' . $status . ' / ' . $view);
    }
    $waiting = $linkedRead; $waiting['groups'][$group]['status'] = 'waiting';
    $waiting['groups'][$group]['registration_scope'] = 'period';
    $waitingHtml = (new Renderer())->render($waiting, ['rnl_view' => $view]);
    $assert(str_contains($waitingHtml, 'Se venteliste hos LetsReg') && str_contains($waitingHtml, 'Lenken viser flere kurs.'), 'Waitlist/period destination is misleading: ' . $view);
    $waiting['groups'][$group]['registration_url'] = '';
    $assert(!str_contains((new Renderer())->render($waiting, ['rnl_view' => $view]), 'data-rnl-letsreg='), 'Missing URL still renders registration link');
}
$detail=(new Renderer())->render($read,['rnl_course'=>$group,'rnl_period'=>$period,'rnl_level'=>123,'rnl_day'=>'1','rnl_view'=>'week']);
$assert(str_contains($detail,'Meld deg på hos LetsReg') && str_contains($detail,'Alle kurskveldene'),'Detaljen mangler datoer eller påmelding.');
$assert(strpos($detail, 'rnl-booking') < strpos($detail, 'Lær grunntrinnene sammen.')
    && strpos($detail, 'Oppstart: 7. januar 2030') < strpos($detail, 'Lær grunntrinnene sammen.')
    && strpos($detail, 'Meld deg på hos LetsReg') < strpos($detail, 'Lær grunntrinnene sammen.'),
    'Fakta, faktisk oppstart og påmelding skal komme før lang beskrivelse i leserekkefølgen.');
$assert(str_contains($detail,'rnl_view=week') && str_contains($detail,'rnl_level=123') && str_contains($detail,'#rnl-course-'.$group),'Retur mister valg/fokusmål.');
// Text belongs to named profile sections, while overview cards retain only the short level pill.
$layoutRead=$read; $layoutRead['groups'][$group]['level_name']='Nivåprøve';
$layoutRead['groups'][$group]['level_help']='Ekstra nivåveiledning.';
$layoutHtml=(new Renderer())->render($layoutRead,['rnl_course'=>$group]);
$dom=new DOMDocument(); $previousErrors=libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">'.$layoutHtml); libxml_clear_errors(); libxml_use_internal_errors($previousErrors);
$xp=new DOMXPath($dom);
$main="//*[contains(concat(' ',normalize-space(@class),' '),' rnl-course-description ')]";
$headings=[];foreach($xp->query($main.'/section/h3')as$heading){$headings[]=$heading->textContent;}
$assert(array_slice($headings,0,4)===['Kursbeskrivelse','Nivå og forkunnskaper','Partnerinformasjon','Prisvilkår og tillegg'],'Profile description sections missing or out of order');
$hero=$xp->query('//header')->item(0);
$assert(!str_contains($hero->textContent,$g['level'])&&!str_contains($hero->textContent,'Ekstra nivåveiledning.'),'Long level text remains in profile header');
$assert($xp->query('//header//span[contains(@class,"rnl-course-pill-style")]')->length===1&&$xp->query('//header//span[contains(@class,"rnl-course-pill-level")]')->length===1,'Style and level pills missing beside profile title');
$assert(!str_contains($xp->query('//aside')->item(0)->textContent,$g['price_terms']),'Price terms remain in sidebar');
$assert(str_contains($xp->query($main)->item(0)->textContent,'Ekstra nivåveiledning.'),'Level guidance was lost instead of moved');
$assert($xp->query($main.'/section[@id="rnl-location-'.$group.'"]')->length===1,'Location panel missing from main column');
$assert($xp->query('//aside//a[@href="#rnl-location-'.$group.'"]')->length===1,'Sidebar address does not link to location panel');
$assert(str_contains($xp->query($main.'/section[@id="rnl-location-'.$group.'"]')->item(0)->textContent,$g['address']),'Address missing without coordinates');

foreach(['list','week']as$view){
 $layoutHtml=(new Renderer())->render($layoutRead,['rnl_period'=>$period,'rnl_view'=>$view]);
 $previousErrors=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$layoutHtml);libxml_clear_errors();libxml_use_internal_errors($previousErrors);$xp=new DOMXPath($dom);
 $card=$xp->query('//article[@id="rnl-course-'.$group.'"]')->item(0);
 $assert($card&&!str_contains($card->textContent,$g['level'])&&!str_contains($card->textContent,$g['price_terms']),'Overview repeats level explanation or price terms: '.$view);
 $headingTag=$view==='week'?'h4':'h3';
 $assert($xp->query('./'.$headingTag.'/following-sibling::*[1][contains(@class,"rnl-course-pills")]/span[contains(@class,"rnl-course-pill-level")]',$card)->length===1,'Level pill is not immediately below card title: '.$view);
}
$schema=SchemaPresenter::group($g,PublicSite::url($group));
$assert(count($schema['hasCourseInstance']['courseSchedule'])===3 && !isset($schema['hasCourseInstance']['eventSchedule']),'Schema må beskrive faktiske økter uten dobbelt kalenderskjema.');
$assert($schema['hasCourseInstance']['offers']['price']==='1234.50','Schema-pris avviker fra visningen.');
$evil=$g; $evil['description']='</script><script>alert(1)</script>'; $assert(!str_contains(SchemaPresenter::script(SchemaPresenter::group($evil,PublicSite::url($group))),'</script><script>'),'JSON-LD kan brytes ut av script-tag.');
$week=(new Renderer())->render($read,['rnl_view'=>'week']);
$assert(str_contains($week,'Mandager') && str_contains($week,'Sal 1') && !str_contains(strip_tags($week),'2030-01-07'),'Ukeskalenderen viser feil mønster eller konkrete datoer.');
// A single evening is a date, not a weekly series, including early/late intro courses.
$singleRead = $read;
$single = array_replace($g, ['weekday'=>6, 'start_time'=>'13:00', 'end_time'=>'15:30', 'count'=>1,
    'first'=>'2030-01-05T12:00:00Z', 'last'=>'2030-01-05T14:30:00Z', 'early_start'=>false, 'delayed_start'=>false]);
$single['sessions'] = [array_replace($g['sessions'][0], ['date'=>'2030-01-05', 'original_date'=>'2030-01-05',
    'starts_at'=>$single['first'], 'ends_at'=>$single['last'], 'start_time'=>'13:00', 'end_time'=>'15:30'])];
$singleRead['groups'] = [$group=>$single];
$singleList = (new Renderer())->render($singleRead, []);
$assert(str_contains($singleList, 'Lørdag · 13:00–15:30') && !str_contains($singleList, 'Lørdager'), 'En enkelt kveld beskrives som et gjentakende kurs på kortet.');
$assert(str_contains($singleList, '<dt>Dato</dt>') && str_contains($singleList, '5. januar 2030 · 1 kveld') && !str_contains($singleList, '<dt>Oppstart</dt>'), 'Kurskortet mangler dato/entallsantall for én kveld.');
$singleDetail = (new Renderer())->render($singleRead, ['rnl_course'=>$group]);
$assert(str_contains($singleDetail, 'Dato: 5. januar 2030') && str_contains($singleDetail, 'Lørdag 13:00–15:30')
    && str_contains($singleDetail, '1 kurskveld') && !str_contains($singleDetail, 'Oppstart:') && !str_contains($singleDetail, 'Lørdager'), 'Kursprofilen bruker oppstart/flertallsdag for én kveld.');
foreach (['normal', 'early_start', 'delayed_start'] as $startKind) {
    $singleRead['groups'][$group] = $single;
    if ($startKind !== 'normal') { $singleRead['groups'][$group][$startKind] = true; }
    $singleWeek = (new Renderer())->render($singleRead, ['rnl_view'=>'week']);
    $assert(str_contains($singleWeek, '<h3>Lørdag</h3>') && str_contains($singleWeek, 'Dato: 5. januar 2030')
        && !str_contains($singleWeek, 'oppstart:'), 'Kalenderen mangler enkeltdato eller bruker oppstart for én kveld: ' . $startKind);
}
$assert(str_contains($html, 'Mandager · 18:00–19:00') && str_contains($html, '<dt>Oppstart</dt>')
    && str_contains($detail, 'Oppstart: 7. januar 2030') && str_contains($detail, 'Mandager 18:00–19:00'), 'Kurs over flere kvelder mistet flertall eller oppstart.');
// Each day has its own extent. Rooms within the day retain a shared clock, including gaps.
$lateId = $group + 1000000; $saturdayId = $group + 1000001; $tuesdayId = $group + 1000002;
$dailyRead = $read;
$dailyRead['groups'][$lateId] = array_replace($g, ['id'=>$lateId, 'room_id'=>$room + 1000000, 'room'=>'Sal 2', 'start_time'=>'19:30', 'end_time'=>'20:15', 'level_id'=>101]);
$dailyRead['groups'][$saturdayId] = array_replace($single, ['id'=>$saturdayId]);
$dailyRead['groups'][$tuesdayId] = array_replace($g, ['id'=>$tuesdayId, 'weekday'=>2, 'start_time'=>'18:10', 'end_time'=>'19:05']);
$parseCalendar = static function(string $markup): DOMXPath {
    $document = new DOMDocument(); $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$markup); libxml_clear_errors(); libxml_use_internal_errors($previous);
    return new DOMXPath($document);
};
$dayPath = '//section[contains(concat(" ",normalize-space(@class)," ")," rnl-day ")]';
$dailyXp = $parseCalendar((new Renderer())->render($dailyRead, ['rnl_view'=>'week']));
foreach ([['Mandager', 135, ['18:00','18:30','19:00','19:30','20:00']], ['Tirsdager', 55, ['18:10','18:40']], ['Lørdag', 150, ['13:00','13:30','14:00','14:30','15:00']]] as [$heading, $duration, $expectedTicks]) {
    $dayNode = $dailyXp->query($dayPath.'[h3="'.$heading.'"]')->item(0);
    $assert($dayNode !== null, 'Mangler dagsoverskrift: '.$heading);
    $table = $dailyXp->query('./div[contains(@class,"rnl-timetable")]', $dayNode)->item(0);
    $assert(str_contains($table->getAttribute('style'), '--rnl-rows:'.$duration), 'Dagens tidsakse påvirkes av kurs på andre dager: '.$heading);
    $actualTicks = [];
    foreach ($dailyXp->query('./div[contains(@class,"rnl-time-tick")]', $table) as $tick) { $actualTicks[] = $tick->textContent; }
    $assert($actualTicks === $expectedTicks, 'Tomtid før/etter dagens kurs eller feil minuttplassering: '.$heading);
}
$firstCard = $dailyXp->query('//article[@id="rnl-course-'.$group.'"]')->item(0);
$laterCard = $dailyXp->query('//article[@id="rnl-course-'.$lateId.'"]')->item(0);
$assert(str_contains($firstCard->getAttribute('style'), 'grid-row:2 / 62') && str_contains($laterCard->getAttribute('style'), 'grid-row:92 / 137'), 'Saler på samme dag mister felles klokke eller oppholdet mellom kurs.');
$assert(str_contains($firstCard->getAttribute('style'), 'grid-column:2') && str_contains($laterCard->getAttribute('style'), 'grid-column:3'), 'Saler havner ikke i separate kolonner.');
$filteredXp = $parseCalendar((new Renderer())->render($dailyRead, ['rnl_view'=>'week', 'rnl_level'=>101]));
$assert($filteredXp->query($dayPath)->length === 1 && $filteredXp->query('//div[contains(@class,"rnl-time-tick")]')->item(0)->textContent === '19:30'
    && str_contains($filteredXp->query('//div[contains(@class,"rnl-timetable")]')->item(0)->getAttribute('style'), '--rnl-rows:45'), 'Filtrerte bort kurs legger fortsatt til tomtid.');
$mixedRead = $singleRead;
$mixedRead['groups'][$lateId] = array_replace($g, ['id'=>$lateId, 'weekday'=>6, 'start_time'=>'18:00', 'end_time'=>'19:00']);
$mixedWeek = (new Renderer())->render($mixedRead, ['rnl_view'=>'week']);
$assert(str_contains($mixedWeek, '<h3>Lørdager</h3>') && str_contains($mixedWeek, 'Dato: 5. januar 2030'), 'Blandet dag må beholde flertallsoverskrift og datomerking på enkeltkurset.');
// Avada's content pipeline may apply wpautop after the pretty-URL render.
// Time labels must remain independent grid children, including after repeated formatting.
foreach ([$week, wpautop($week), wpautop(wpautop($week))] as $formattedWeek) {
    $previousErrors=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$formattedWeek);libxml_clear_errors();libxml_use_internal_errors($previousErrors);$xp=new DOMXPath($dom);
    $ticks=$xp->query('//*[contains(@class,"rnl-time-tick")]');
    $direct=$xp->query('//*[contains(@class,"rnl-timetable")]/*[contains(@class,"rnl-time-tick")]');
    $assert($ticks->length>1 && $ticks->length===$direct->length,'Time labels were grouped into a paragraph by content formatting');
    $assert($xp->query('//*[contains(@class,"rnl-timetable")]/*[contains(@class,"rnl-time-heading")]')->length>0,'Time column heading lost its grid placement');
}
$restricted=(new Renderer())->render($read,['rnl_view'=>'week'],'list',['list']); $assert(!str_contains($restricted,'rnl-timetable'),'URL overstyrer innbyggingens tillatte visninger.');
$defaultRead=$read; $defaultRead['periods'][$period]['default_view']='week'; $assert(str_contains((new Renderer())->render($defaultRead,[],'period'),'rnl-timetable'),'Periodens standardvisning blir ignorert.');
$filtered=(new Renderer())->render($read,['rnl_level'=>999999999]); $assert(str_contains($filtered,'Ingen kurs passer akkurat'),'Tomt filter har ingen forklaring.');
$clock->time='2030-01-06T10:00:00Z'; $g=$catalog->read()['groups'][$group]; $assert($g['status']==='closed' && $g['registration_url']==='','Salg stenger ikke ved eksakt grense.');
$clock->time='2030-01-10T10:00:00Z'; $read=$catalog->read(); $assert($read['current']===[$period],'Pause mellom økter mister gjeldende periode.');
$clock->time='2030-02-01T10:00:00Z'; $read=$catalog->read(); $assert($read['default']===null && isset($read['groups'][$group]) && $read['groups'][$group]['status']==='ended','Avsluttet periode skal være direkte lesbar, men ikke foreslått.');
$clock->time='2030-03-01T10:00:00Z'; $assert($catalog->read()['groups']===[],'Utgått kurs er fortsatt offentlig.');
wp_set_current_user($admin); $clock->time='2030-01-03T10:00:00Z';
$state=$repo->get($period); $versions=array_map(static fn($g)=>$g['version'],$repo->groups($period)); $draft=$repo->lifecycle($period,$state['version'],$versions,'draft');
wp_set_current_user(0); $assert($catalog->read()['groups']===[],'Kladd eksponeres.');
wp_set_current_user($admin); $g=$repo->get($group); $first=$g['data']['sessions'][0];
$repo->confirm($repo->previewSessionChange($group,$g['version'],$first['id'],['date'=>'2030-01-08','status'=>'moved','reason'=>'Flyttet etter avtale']));
$repo->publish($repo->previewPublication($period,$draft['version'],true)); wp_set_current_user(0); $g=$catalog->read()['groups'][$group];
$assert($g['sessions'][0]['date']==='2030-01-08' && $g['first']==='2030-01-08T17:00:00Z','Offentlig modell bruker ikke faktisk flytting.');
$assert(SchemaPresenter::group($g,PublicSite::url($group))['hasCourseInstance']['courseSchedule'][0]['startDate']==='2030-01-08','Schema overser flytting.');
$assert(!isset(rest_get_server()->get_routes()['/wp/v2/rnl_groups']),'Generisk REST er eksponert.');
wp_set_current_user($admin); wp_update_post(['ID'=>$teacher,'post_status'=>'draft']); wp_set_current_user(0);
$assert($catalog->read()['groups']===[],'Privat profil lekker gjennom offentlig modell.');
wp_set_current_user($admin); wp_update_post(['ID'=>$teacher,'post_status'=>'publish']);
wp_update_post(['ID'=>$teacher,'post_password'=>'synthetic-only']); wp_set_current_user(0); $assert($catalog->read()['groups']===[],'Passordbeskyttet profil skal ikke regnes som offentlig.'); wp_set_current_user($admin); wp_update_post(['ID'=>$teacher,'post_password'=>'']);
$old=get_post_meta($group,ContentTypes::META,true); $old['data']['registration_url']='https://phishing.example/test'; update_post_meta($group,ContentTypes::META,wp_slash($old));
wp_set_current_user(0); $assert($catalog->read()['groups'][$group]['registration_url']==='','Ugodkjent målvert åpnes offentlig.');
$assert(shortcode_exists('reginor_courses') && WP_Block_Type_Registry::get_instance()->is_registered('reginor-lite/courses'),'Innbygging mangler.');
$assert(SchemaPresenter::group($g,PublicSite::url($group))['hasCourseInstance']['subEvent'][0]['location']['address']['streetAddress']==='Testgata 12','Underhendelser mangler faktisk sted.');
$assert(get_post_type_object('rnl_group')->publicly_queryable===false,'Rå CPT skal fortsatt være lukket.');
// Course windows narrow period dates, update expiry and clear the public URL at the boundary.
wp_set_current_user($admin);
$state=$repo->get($period); $versions=array_map(static fn($g)=>$g['version'],$repo->groups($period));
$draft=$repo->lifecycle($period,$state['version'],$versions,'draft');
$state=$repo->get($group);
$repo->confirm($repo->previewGroup($group,$state['version'],['registration_url'=>'https://www.letsreg.com/event/test','price_from'=>true,
    'registration_from'=>'2030-01-03T10:00:00Z','registration_until'=>'2030-01-05T10:00:00Z']));
$repo->publish($repo->previewPublication($period,$draft['version'],true));
$clock->time='2030-01-03T09:59:59Z';
$assert($repo->salesStatus($group)==='later','Admin status ignores the course opening date');
wp_set_current_user(0); $read=$catalog->read();
$assert($read['groups'][$group]['status']==='later' && $read['groups'][$group]['registration_url']==='','Course sale opens too early');
$assert($read['expires_at']===strtotime('2030-01-03T10:00:00Z'),'Open page does not expire at course opening');
$openingHtml=(new Renderer())->render($read,['rnl_course'=>$group]);
$assert(str_contains($openingHtml,'Påmeldingen åpner 3. januar 2030 kl. 11:00')
    && !str_contains($openingHtml,'Påmeldingen åpner 2. januar'), 'Detaljen viser periodens åpning i stedet for kursets effektive åpning.');
$editorial=$read; $editorial['groups'][$group]['registration_from']=null;
$editorialHtml=(new Renderer())->render($editorial,['rnl_course'=>$group]);
$assert(!str_contains($editorialHtml,'Påmeldingen åpner 2. januar'), 'Redaksjonell senere-status annonserer en passert åpning.');
$inverted=$read; $inverted['groups'][$group]['registration_until']='2030-01-02T12:00:00Z';
$assert(!str_contains((new Renderer())->render($inverted,['rnl_course'=>$group]),'Påmeldingen åpner 3. januar'), 'Et tomt salgsvindu annonseres som en kommende åpning.');
$clock->time='2030-01-03T10:00:00Z'; $read=$catalog->read(); $g=$read['groups'][$group];
$assert($g['status']==='available' && $g['registration_url']!=='','Course sale does not open at boundary');
$assert($read['expires_at']===strtotime('2030-01-05T10:00:00Z'),'Open page does not expire at course closing');
$html=(new Renderer())->render($read,[]);
$assert(str_contains($html,'Fra ' . number_format_i18n(1234.5, 2)), 'Public price lacks from-price label');
$offer=SchemaPresenter::group($g,PublicSite::url($group))['hasCourseInstance']['offers'];
$assert(!isset($offer['price']) && str_contains($offer['description'],'Fra '), 'Minimum price is asserted as an exact schema price');
$clock->time='2030-01-05T10:00:00Z'; $g=$catalog->read()['groups'][$group];
$assert($g['status']==='closed' && $g['registration_url']==='','Course closing leaves registration link open');
wp_set_current_user($admin);
$copy=$created[]=$repo->copyPeriod($period,$repo->get($period)['version'],'Public copy','2030-04-01');
foreach ($repo->groups($copy) as $copyId=>$copyState) {
    $created[]=$copyId;
    $assert($copyState['data']['registration_from']===null && $copyState['data']['registration_until']===null, 'Copy retains old course registration window');
}
} finally {
wp_set_current_user($admin); remove_filter('rnl_instructor_post_types',$types);
foreach(array_reverse($created) as $id){wp_delete_post($id,true);} if($oldPage===null)delete_option('rnl_course_page_id');else update_option('rnl_course_page_id',$oldPage);
wp_set_current_user($original);
}
WP_CLI::success('Offentlige kontroller bestått: '.$checks.'. Testobjekter er ryddet bort.');
