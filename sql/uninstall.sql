-- CDN Tax Receipts Extension
-- last change: 0.9.beta1

DROP TABLE IF EXISTS cdntaxreceipts_log_contributions;
DROP TABLE IF EXISTS cdntaxreceipts_log;

-- Delete message template and respective option group and option values
DELETE  civicrm_option_value.*, civicrm_option_group.*, civicrm_msg_template.*
FROM civicrm_option_value
INNER JOIN civicrm_option_group ON  civicrm_option_value.option_group_id = civicrm_option_group.id
INNER JOIN civicrm_msg_template ON civicrm_msg_template.workflow_id = civicrm_option_value.id
WHERE civicrm_option_group.name = 'msg_tpl_workflow_cdntaxreceipts';
