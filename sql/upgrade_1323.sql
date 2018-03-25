-- Update file path for receipt_logo, receipt_signature, receipt_watermark, receipt_pdftemplate
UPDATE `civicrm_setting`
  SET value = CONCAT(
    's:',
    CHAR_LENGTH(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(value, ':"', -1), '/', -1), '";', '')),
    ':"',
    REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(value, ':"', -1), '/', -1), '";', ''),
    '";'
    )
  WHERE name IN ('receipt_logo', 'receipt_signature', 'receipt_watermark', 'receipt_pdftemplate');
