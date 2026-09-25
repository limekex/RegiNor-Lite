<?php
/** Isolated subprocess: intentionally exits/fails, without WordPress or a database. */
declare(strict_types=1);

use RegiNor\Lite\Infrastructure\MutationTrace;

require dirname(__DIR__, 2) . '/plugin/reginor-lite/src/autoload.php';
function get_current_blog_id(): int { return 19; }
ini_set('error_log', $argv[2]);
$mode = $argv[1];
if ($mode !== 'disabled') { define('RNL_TRACE_MUTATIONS', true); }
define('RNL_TRACE_SITE_ID', $mode === 'wrong_site' ? 1 : 19);
MutationTrace::begin();
MutationTrace::transaction(true);
MutationTrace::phase('metadata.write', 123);
// Unknown markers cannot accidentally place content in diagnostics.
MutationTrace::phase('SECRET_SENTINEL payload@example.invalid');
if ($mode === 'fatal') { trigger_error('SECRET_SENTINEL fatal payload', E_USER_ERROR); }
if ($mode === 'exit') { exit; }
MutationTrace::transaction(false);
MutationTrace::phase('committed');
MutationTrace::finish();
