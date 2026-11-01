<?php
/* Vstupní bod brány AI konzultanta (zkopírujte do webrootu, např. api/ai/chat.php, a upravte cesty).
   Kód a konfigurace leží mimo webroot, sem patří jen tyto řádky. Konfigurace se načte PŘED zúžením
   open_basedir (smí si přečíst např. heslo SMTP z jiné neveřejné složky), potom PHP smí jen do webrootu a $private. */
require __DIR__ . '/../../vendor/ts/ts-ai-consultant/core/bootstrap.php';
$private = __DIR__ . '/../../../private';
$settings = require $private . '/ai-consultant.php';
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [__DIR__ . '/../..', $private]])) return;
ts_ai_consultant($settings)->serve();
