<?php
declare(strict_types=1);
$ADMIN_STORE = array (
  'id' => 1,
  'username' => 'admin',
  'password' => 'admin',
  'roles' => 
  array (
    0 => 'admin',
  ),
  'updated_at' => '2025-11-01T13:14:07+00:00',
  'site_name' => '123123',
  'site_keywords' => '555',
  'site_description' => '666',
  'selected_template' => 't1',
);
$isDirect = isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
if ($isDirect) { header('Content-Type: application/json; charset=UTF-8'); echo json_encode($ADMIN_STORE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
return $ADMIN_STORE;
