<?php

function pacs_pick_tag(array $tags, string $name): string {
  $v = $tags[$name] ?? '';
  if (is_array($v)) {
    $v = $v[0] ?? '';
  }
  return trim((string)$v);
}

function pacs_build_study_meta(array $instanceTags, array $studyTags): array {
  $merged = array_merge($studyTags, $instanceTags);
  return [
    'patient_name' => pacs_pick_tag($merged, 'PatientName'),
    'patient_id' => pacs_pick_tag($merged, 'PatientID'),
    'study_date' => pacs_pick_tag($merged, 'StudyDate'),
    'modality' => pacs_pick_tag($merged, 'Modality'),
    'study_uid' => pacs_pick_tag($merged, 'StudyInstanceUID'),
  ];
}
