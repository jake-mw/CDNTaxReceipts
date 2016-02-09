<div class="crm-block crm-form-block crm-miscellaneous-form-block">

<h3>{ts domain='org.civicrm.cdntaxreceipts'}Organization Details{/ts}</h3>

  <table class="form-layout">
    <tbody>
      <tr>
        <td class="label">{$form.org_name.label}</td>
        <td class="content">{$form.org_name.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}My Charitable Organization{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_address_line1.label}</td>
        <td class="content">{$form.org_address_line1.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}101 Anywhere Drive{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_address_line2.label}</td>
        <td class="content">{$form.org_address_line2.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Toronto ON A1B 2C3{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_tel.label}</td>
        <td class="content">{$form.org_tel.html}
          <p class="description">(555) 555-5555</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_fax.label}</td>
        <td class="content">{$form.org_fax.html}
          <p class="description">(555) 555-5555</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_email.label}</td>
        <td class="content">{$form.org_email.html}
          <p class="description">info@my.org</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_web.label}</td>
        <td class="content">{$form.org_web.html}
          <p class="description">www.my.org</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_charitable_no.label}</td>
        <td class="content">{$form.org_charitable_no.html}
          <p class="description">10000-000-RR0000</p></td>
      </tr>
      <tr>
        <td class="label">{$form.org_location_issued.label}</td>
        <td class="content">{$form.org_location_issued.html}
          <p class="description">City, town, or municipality</p></td>
      </tr>
    </tbody>
  </table>

<h3>{ts domain='org.civicrm.cdntaxreceipts'}Receipt Configuration{/ts}</h3>

  <table class="form-layout">
    <tbody>
      <tr>
        <td class="label">{$form.receipt_prefix.label}</td>
        <td class="content">{$form.receipt_prefix.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Receipt numbers are formed by appending the CiviCRM Contribution ID to this prefix. Receipt numbers must be unique within your organization. If you also issue tax receipts using another system, you can use the prefix to ensure uniqueness (e.g. enter 'WEB-' here so all receipts issued through CiviCRM are WEB-00000001, WEB-00000002, etc.){/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.receipt_authorized_signature_text.label}</td>
        <td class="content">{$form.receipt_authorized_signature_text.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Name and position of the authorizing official to be displayed under the signature line. Defaults to "Authorized Signature" if no name is specified.{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.receipt_logo.label}</td>
        <td class="content">{$form.receipt_logo.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Logo size: 280x120 pixels; File types allowed: .jpg .png.{/ts}</p>
	  {if $receipt_logo}
	      {if $receipt_logo_class}<span class="crm-error">The file {$receipt_logo} was not found</span>
	      {else}<p class="label">Current {$form.receipt_logo.label}: {$receipt_logo}</p>{/if}
	  {/if}</td>
      </tr>
      <tr>
        <td class="label">{$form.receipt_signature.label}</td>
        <td class="content">{$form.receipt_signature.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Signature size: 141x58 pixels; File types allowed: .jpg .png.{/ts}</p>
	  {if $receipt_signature}
	      {if $receipt_signature_class}<span class="crm-error">The file {$receipt_signature} was not found</span>
	      {else}<p class="label">Current {$form.receipt_signature.label}: {$receipt_signature}</p>{/if}
	  {/if}</td>
      </tr>
      <tr>
        <td class="label">{$form.receipt_watermark.label}</td>
        <td class="content">{$form.receipt_watermark.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Watermark Image size: 250x250 pixels; File types allowed: .jpg .png.{/ts}</p>
	  {if $receipt_watermark}
	      {if $receipt_watermark_class}<span class="crm-error">The file {$receipt_watermark} was not found</span>
	      {else}<p class="label">Current {$form.receipt_watermark.label}: {$receipt_watermark}</p>{/if}
	  {/if}</td>
      </tr>
      <tr>
        <td class="label">{$form.receipt_pdftemplate.label}</td>
        <td class="content">{$form.receipt_pdftemplate.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Upload your own PDF template: .pdf{/ts}</p>
	  {if $receipt_pdftemplate}
	      {if $receipt_pdftemplate_class}<span class="crm-error">The file {$receipt_pdftemplate} was not found</span>
	      {else}<p class="label">Current {$form.receipt_pdftemplate.label}: {$receipt_pdftemplate}</p>{/if}
	  {/if}</td>
      </tr>
    </tbody>
  </table>

<h3>{ts domain='org.civicrm.cdntaxreceipts'}System Options{/ts}</h3>

  <table class="form-layout">
    <tbody>
      <tr>
        <td class="label">{$form.issue_inkind.label}</td>
        <td class="content">{$form.issue_inkind.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Checking this box will set up the fields required to generate in-kind tax receipts. Unchecking the box will not disable in-kind receipts: you will need to do that manually, by disabling the In-kind contribution type or making it non-deductible in the CiviCRM administration pages.{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.enable_email.label}</td>
        <td class="content">{$form.enable_email.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}If enabled, tax receipts will be sent via email to donors who have an email address on file.{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.enable_advanced_eligibility_report.label}</td>
        <td class="content">{$form.enable_advanced_eligibility_report.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}If enabled, the Receipts not issued Report will have the Advanced Eligibility Check enabled by default.{/ts}</p></td>
      </tr>
    </tbody>
  </table>

<h3>{ts domain='org.civicrm.cdntaxreceipts'}Email Message{/ts}</h3>

  <table class="form-layout">
    <tbody>
      <tr>
        <td class="label">{$form.email_subject.label}</td>
        <td class="content">{$form.email_subject.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Subject of the Email to accompany your Tax Receipt. The receipt number will be appended.{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.email_from.label}</td>
        <td class="content">{$form.email_from.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Address you would like to Email the Tax Receipt from.{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.email_archive.label}</td>
        <td class="content">{$form.email_archive.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Address you would like to Send a copy of the Email containing the Tax Receipt to. This is useful to create an archive.{/ts}</p></td>
      </tr>
      <tr>
        <td class="label">{$form.email_message.label}</td>
        <td class="content">{$form.email_message.html}
          <p class="description">{ts domain='org.civicrm.cdntaxreceipts'}Text in the Email to accompany your Tax Receipt.{/ts}</p></td>
      </tr>
    </tbody>
  </table>

<div class="status message"><strong>Tip:</strong>{ts domain='org.civicrm.cdntaxreceipts'}After you fill out this form and save your Configuration, create a fake Donation in CiviCRM and issue a Tax Receipt for it to check the graphics/layout of the Tax Receipt that is generated. If necessary - rework your graphics and come back to this Form to upload the new version(s).{/ts}</div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

</div>
