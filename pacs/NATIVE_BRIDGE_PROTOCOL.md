# Native DicomViewer Bridge Protocol

AMS PACS membuka native viewer dengan URL:

adena-dicom://open?study_uid=<StudyInstanceUID>&patient_id=<DICOMPatientID>&patient_name=<PatientName>&ams_patient_id=<AMSPatientID>&ams_visit_id=<AMSVisitID>&pacs_api=<AMS_API_URL>

Parameter wajib:
- study_uid

Parameter opsional:
- patient_id
- patient_name
- ams_patient_id
- ams_visit_id
- pacs_api

Native DicomViewer perlu mendaftarkan custom protocol `adena-dicom` di Windows/Electron dan membaca parameter tersebut untuk membuka study yang sesuai.
