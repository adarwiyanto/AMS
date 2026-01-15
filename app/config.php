<?php
return array (
  'installed' => true,
  'base_path' => '/AMS',
  'db' => 
  array (
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'ams11',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
  ),
  'security' => 
  array (
    'session_name' => 'AMSSESSID',
    'csrf_key' => '922e70ba681779f204f2c06607a7a469',
  ),
  'uploads' => 
  array (
    'logo_dir' => 'C:\\xampp\\htdocs\\AMS\\install/../storage/uploads/logo',
    'signature_dir' => 'C:\\xampp\\htdocs\\AMS\\install/../storage/uploads/signature',
  ),
  'paths' => 
  array (
    'logs' => 'C:\\xampp\\htdocs\\AMS\\install/../storage/logs',
    'backups' => 'C:\\xampp\\htdocs\\AMS\\install/../storage/backups',
  ),
  'gdrive' => 
  array (
    'enabled' => false,
    'service_account_json' => 'C:\\xampp\\htdocs\\AMS\\install/../storage/credentials/gdrive_service_account.json',
    'folder_id' => '',
  ),
);
