<?php
return [
  'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'adey8293_pacs',
    'user' => 'adey8293_adyto',
    'pass' => '', // isi sendiri oleh Dok di app/pacs_config.php
    'charset' => 'utf8mb4',
  ],

  'storage' => [
    'path' => '/home/adey8293/private_uploads/ams_pacs',
  ],

  // Native bridge: browser akan membuka aplikasi desktop melalui custom protocol.
  // DicomViewer native perlu didaftarkan untuk protocol adena-dicom://open.
  'native_bridge' => [
    'enabled' => true,
    'protocol' => 'adena-dicom://open',
  ],
];
