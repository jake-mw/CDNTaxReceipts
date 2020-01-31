{* Confirmation of tax receipts  *}
<div class="crm-block crm-form-block crm-contact-task-delete-form-block">
<div class="messages status no-popup">
  <div class="icon inform-icon"></div>
  {ts 1=$totalSelectedContributions 2=$receiptList.totals.original 3=$receiptList.totals.duplicate domain='org.civicrm.cdntaxreceipts' }
    You have selected <strong>%1</strong> contributions including <strong>%2</strong> originals and <strong>%3</strong>
    duplicates to issue. Note that duplicates cannot be issued by this method and will be skipped.
    The summary below includes the original receipt issue contributions only.{/ts}
</div>
  <table id="cdntax_original_summary" class="cdntax_summary">
    <thead>
      <th width='8%'>{ts domain='org.civicrm.cdntaxreceipts'}Tax Year{/ts}</th>
      <th width='8%'>{ts domain='org.civicrm.cdntaxreceipts'}# of Contributors{/ts}</th>
      <th width='10%'>{ts domain='org.civicrm.cdntaxreceipts'}Selected Contributions{/ts}</th>
      <th width='10%'>{ts domain='org.civicrm.cdntaxreceipts'}Selected Contribution Amount{/ts}</th>
  {if $deliveryMethod neq '2'}
      <th width='15%'>{ts domain='org.civicrm.cdntaxreceipts'}Email{/ts}</th>
      <th width='15%'>{ts domain='org.civicrm.cdntaxreceipts'}Print{/ts}</th>
  {else}
      <th width='30%'>{ts domain='org.civicrm.cdntaxreceipts'}Tax Receipts{/ts}</th>
  {/if}
      <th width='12%'>{ts domain='org.civicrm.cdntaxreceipts'}Contributions Not Eligible{/ts}</th>
      <th width='12%'>{ts domain='org.civicrm.cdntaxreceipts'}Not Eligible Amount{/ts}</th>
      <th width='10%'>{ts domain='org.civicrm.cdntaxreceipts'}Total Amount Issued{/ts}</th>
    </thead>
    {foreach from=$receiptYears item=year}
      {assign var="key" value="issue_$year"}
      <tr class="{cycle values="odd-row,even-row"}">
        <td>{$form.receipt_year.$key.html}</td>
        <td>{$receiptList.original.$year.total_contacts}</td>
        <td>{$receiptList.original.$year.total_contrib}</td>
        <td>{$receiptList.original.$year.total_amount|crmMoney}</td>
  {if $deliveryMethod neq '2'}
        <td>{$receiptList.original.$year.email.receipt_count} ({$receiptList.original.$year.email.contribution_count} contributions)</td>
        <td>{$receiptList.original.$year.print.receipt_count} ({$receiptList.original.$year.print.contribution_count} contributions)</td>
  {else}
        <td>{$receiptList.original.$year.data.receipt_count} ({$receiptList.original.$year.data.contribution_count} contributions)</td>
  {/if}
        <td>{$receiptList.original.$year.not_eligible}</td>
        <td>{$receiptList.original.$year.not_eligible_amount|crmMoney}</td>
        {math equation="x - y" x=$receiptList.original.$year.total_amount y=$receiptList.original.$year.not_eligible_amount assign="total_issue"}
        <td>{$total_issue|crmMoney}</td>
      </tr>
    {/foreach}
  </table>

  <p>{ts domain='org.civicrm.cdntaxreceipts'}Clicking 'Issue Tax Receipts' will issue aggregate tax receipts grouped into the selected year(s). These tax receipts are a sum
    total of all selected eligible contributions, received from the donor during the selected year, that have not already been receipted individually.{/ts}</p>
    <p>{ts domain='org.civicrm.cdntaxreceipts'}<strong>This action cannot be undone.</strong> Tax receipts will be logged for auditing purposes,
    and a copy of each receipt will be submitted to the tax receipt archive.{/ts}</p>
  {if $deliveryMethod neq '2'}
    <ul>
      <li>{ts domain='org.civicrm.cdntaxreceipts'}Email receipts will be emailed directly to the contributor.{/ts}</li>
      <li>{ts domain='org.civicrm.cdntaxreceipts'}Print receipts will be compiled into a file for download.  Please print and mail any receipts in this file.{/ts}</li>
    </ul>
    <p>{$form.is_preview.html} {$form.is_preview.label} {ts domain='org.civicrm.cdntaxreceipts'}(Generates receipts marked 'preview', but does not issue the receipts.  No logging or emails sent.){/ts}</p>
  {/if}
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl"}</div>
</div>
