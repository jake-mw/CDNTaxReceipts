{* Confirmation of tax receipts  *}
<div class="crm-block crm-form-block crm-contact-task-delete-form-block">
<div class="messages status no-popup">
  <div class="icon inform-icon"></div>
  {ts domain='org.civicrm.cdntaxreceipts'}You have selected <strong>{$totalSelectedContributions}</strong> contributions. Of these, <strong>{$receiptTotal}</strong> are eligible to receive tax receipts.{/ts}
</div>
  <table>
    <thead>
      <th>{ts domain='org.civicrm.cdntaxreceipts'}{$receiptList.totals.original}Tax Receipt Status{/ts}</th>
      <th>{ts domain='org.civicrm.cdntaxreceipts'}{$receiptList.totals.original}Total{/ts}</th>
{if $deliveryMethod neq '2'}
      <th>{ts domain='org.civicrm.cdntaxreceipts'}{$receiptList.totals.original}Email{/ts}</th>
      <th>{ts domain='org.civicrm.cdntaxreceipts'}{$receiptList.totals.original}Print{/ts}</th>
{/if}
    </thead>
    <tr>
      <td>{ts domain='org.civicrm.cdntaxreceipts'}Not yet receipted{/ts}</td>
      <td>{$originalTotal}</td>
{if $deliveryMethod neq '2'}
      <td>{$receiptCount.original.email}</td>
      <td>{$receiptCount.original.print}</td>
{/if}
    </tr>
    <tr>
      <td>{ts domain='org.civicrm.cdntaxreceipts'}Already receipted<{/ts}/td>
      <td>{$duplicateTotal}</td>
{if $deliveryMethod neq '2'}
      <td>{$receiptCount.duplicate.email}</td>
      <td>{$receiptCount.duplicate.print}</td>
{/if}
    </tr>
  </table>
  <p>{$form.receipt_option.original_only.html}<br />
     {$form.receipt_option.include_duplicates.html}</p>
  <p>{ts domain='org.civicrm.cdntaxreceipts'}Clicking 'Issue Tax Receipts' will issue the selected tax receipts.
    <strong>This action cannot be undone.</strong> Tax receipts will be logged for auditing purposes,
    and a copy of each receipt will be submitted to the tax receipt archive.{/ts}</p>
{if $deliveryMethod neq '2'}
  <ul>
  <li>{ts domain='org.civicrm.cdntaxreceipts'}Email receipts will be emailed directly to the contributor.{/ts}</li>
  <li>{ts domain='org.civicrm.cdntaxreceipts'}Print receipts will be compiled into a file for download.  Please print and mail any receipts in this file.{/ts}</li>
  </ul></p>
  <p>{$form.is_preview.html} {$form.is_preview.label} {ts domain='org.civicrm.cdntaxreceipts'}(Generates receipts marked 'preview', but does not issue the receipts.  No logging or emails sent.){/ts}</p>
{/if}
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl"}</div>
</div>
