-- Dynamic form field definitions for the request form (Next Step 5).
-- MySQL 5.7 has no ADD COLUMN IF NOT EXISTS — this file is one-shot.
ALTER TABLE `service_catalog`
  ADD COLUMN `fields` JSON DEFAULT NULL;

-- Seed realistic definitions on the two approval-gated catalog items so the
-- dynamic-field UI can be exercised end to end. Idempotent UPDATEs.
UPDATE `service_catalog` SET `fields` = '[
  {"name":"device_type","label":"Device type","type":"select","required":true,"options":["Laptop","Desktop","Monitor","Mobile phone"]},
  {"name":"justification","label":"Business justification","type":"textarea","required":true,"placeholder":"Why do you need this equipment?"},
  {"name":"needed_by","label":"Needed by (YYYY-MM-DD)","type":"text","required":false}
]' WHERE `id` = 4;

UPDATE `service_catalog` SET `fields` = '[
  {"name":"username","label":"VPN username","type":"text","required":true},
  {"name":"access_level","label":"Access level","type":"select","required":true,"options":["Standard","Privileged"]},
  {"name":"business_reason","label":"Business reason","type":"textarea","required":true}
]' WHERE `id` = 6;
