<?php
/** Şablonlar için kısa global yardımcılar (global namespace). */

use App\Core\Helpers;
use App\Core\Tenant;
use App\Core\Csrf;

function e(?string $s): string { return Helpers::e($s); }
function asset(string $p): string { return Helpers::asset($p); }
function turl(string $p = ''): string { return Tenant::url($p); }
function csrf_field(): string { return Csrf::field(); }
function money($v): string { return Helpers::money($v); }
function dur(int $m): string { return Helpers::dur($m); }
