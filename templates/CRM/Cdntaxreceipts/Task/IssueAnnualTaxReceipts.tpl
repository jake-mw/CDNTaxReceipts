{* Confirmation of tax receipts  *}
<div class="crm-block crm-form-block crm-contact-task-delete-form-block">
<div class="messages status no-popup">
  <div class="icon inform-icon"></div>
  {ts 1=$totalSelectedContacts domain='org.civicrm.cdntaxreceipts'}You have selected <strong>%1</strong> contacts. The summary below includes these contacts only.{/ts}
</div>
  <table class="cdntax_summary">
    <thead>
      <th width=30%>{ts domain='org.civicrm.cdntaxreceipts'}Select Tax Year{/ts}</th>
      <th width=30%>{ts domain='org.civicrm.cdntaxreceipts'}Receipts Outstanding{/ts}</th>
{if $deliveryMethod neq '2'}
      <th width=20%>{ts domain='org.civicrm.cdntaxreceipts'}Email{/ts}</th>
      <th>Print</th>
{else}
      <th>Tax Receipts</th>
{/if}
    </thead>
    {foreach from=$receiptYears item=year}
      {assign var="key" value="issue_$year"}
      <tr class="{cycle values="odd-row,even-row"}">
        <td>{$form.receipt_year.$key.html}</td>
        <td>{if $receiptCount.$year.total}{$receiptCount.$year.total} ({$receiptCount.$year.contrib} contributions){else}0{/if}</td>
{if $deliveryMethod neq '2'}
        <td>{$receiptCount.$year.email}</td>
        <td>{$receiptCount.$year.print}</td>
{else}
        <td>{$receiptCount.$year.data}</td>
{/if}
      </tr>
    {/foreach}
  </table>
  <p>{ts domain='org.civicrm.cdntaxreceipts'}Clicking 'Issue Tax Receipts' will issue annual tax receipts for the selected year. Annual tax receipts are a sum
    total of all eligible contributions, received from the donor during the selected year, that have not been receipted individually.{/ts}</p>
  <p>{ts domain='org.civicrm.cdntaxreceipts'}Only one annual tax receipt can be issued per donor, per year. If the donor has eligible contributions that
  were recorded after the annual receipt was issued, those contributions must be receipted one at a time. Use the Find
  Contributions action to issue those receipts.{/ts}</p>
  <p>{ts domain='org.civicrm.cdntaxreceipts'}<strong>This action cannot be undone.</strong> Tax receipts will be logged for auditing purposes,
    and a copy of each receipt will be submitted to the tax receipt archive.{/ts}</p>
{if $deliveryMethod neq '2'}
  <p>
  <ul>
  <li>{ts domain='org.civicrm.cdntaxreceipts'}Email receipts will be emailed directly to the contributor.{/ts}</li>
  <li>{ts domain='org.civicrm.cdntaxreceipts'}Print receipts will be compiled into a file for download.  Please print and mail any receipts in this file.{/ts}</li>
  </ul>
  </p>
  <p>{$form.is_preview.html} {$form.is_preview.label} {ts domain='org.civicrm.cdntaxreceipts'}(Generates receipts marked 'preview', but does not issue the receipts.  No logging or emails sent.){/ts}</p>
{/if}
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl"}</div>
</div>
