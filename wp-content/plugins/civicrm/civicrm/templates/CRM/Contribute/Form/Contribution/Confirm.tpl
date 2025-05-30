{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
{if $action & 1024}
  {include file="CRM/Contribute/Form/Contribution/PreviewHeader.tpl"}
{/if}

<div class="crm-contribution-page-id-{$contributionPageID} crm-block crm-contribution-confirm-form-block" data-page-id="{$contributionPageID}" data-page-template="confirm">
  <div class="help">
    <p>{ts}Please verify the information below carefully. Click <strong>Go Back</strong> if you need to make changes.{/ts}
      {$continueText}
    </p>
  </div>
  <div id="crm-submit-buttons" class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="top"}
  </div>
  {if $is_pay_later}
    <div class="bold pay_later_receipt-section">{$pay_later_receipt}</div>
  {/if}

  {include file="CRM/Contribute/Form/Contribution/MembershipBlock.tpl"}

  {if $amount GTE 0 OR $minimum_fee GTE 0 OR ($isDisplayLineItems and $lineItem)}
    <div class="crm-group amount_display-group">
      <div class="header-dark">
        {if !$membershipBlock AND $amount OR ($isDisplayLineItems and $lineItem)}{ts}Contribution Amount{/ts}{else}{ts}Membership Fee{/ts} {/if}
      </div>

      <div class="display-block">
        {if $isDisplayLineItems && $lineItem}
          {if !$amount}{assign var="amount" value=0}{/if}
          {assign var="totalAmount" value=$amount}
          {include file="CRM/Price/Page/LineItem.tpl" context="Contribution" getTaxDetails=$totalTaxAmount displayLineItemFinancialType=false pricesetFieldsCount=false currencySymbol='' hookDiscount=''}
        {elseif $is_separate_payment}
          {if $amount AND $minimum_fee}
            {$membership_name} {ts}Membership{/ts}:
            <strong>{$membershipTotalAmount|crmMoney}</strong>
            <br/>
            {ts}Additional Contribution{/ts}:
            <strong>{$nonMembershipTotalAmount|crmMoney}</strong>
            <br/>
            <strong> -------------------------------------------</strong>
            <br/>
            {ts}Total{/ts}:
            <strong>{$orderTotal|crmMoney}</strong>
            <br/>
          {elseif $amount}
            {ts}Amount{/ts}:
            <strong>{$amount|crmMoney} {if $amount_level}<span class='crm-price-amount-label'>
                &ndash; {$amount_level}</span>{/if}</strong>
          {else}
            {$membership_name} {ts}Membership{/ts}:
            <strong>{$minimum_fee|crmMoney}</strong>
          {/if}
        {else}
          {if $totalTaxAmount}
            {ts 1=$taxTerm}Total %1 Amount{/ts}:
            <strong>{$totalTaxAmount|crmMoney} </strong>
            <br/>
          {/if}
          {if $amount}
            {if $installments}{ts}Installment Amount{/ts}{else}{ts}Total Amount{/ts}{/if}:
            <strong>{$amount|crmMoney:$currency}{if $amount_level}<span class='crm-price-amount-label'>
                &ndash; {$amount_level}</span>{/if}</strong>
          {else}
            {$membership_name} {ts}Membership{/ts}:
            <strong>{$minimum_fee|crmMoney}</strong>
          {/if}
        {/if}

        {if $is_recur}
          {if !empty($auto_renew)} {* Auto-renew membership confirmation *}
            {crmRegion name="contribution-confirm-recur-membership"}
              <br />
                {if $frequency_interval > 1}
                  {* dev/translation#80 All 'every %1' strings are incorrectly using ts, but focusing on the most important one until we find a better fix. *}
                  {if $frequency_unit eq 'day'}
                    <strong>{ts 1=$frequency_interval 2=$frequency_unit}This membership will be renewed automatically every %1 days.{/ts}</strong>
                  {elseif $frequency_unit eq 'week'}
                    <strong>{ts 1=$frequency_interval 2=$frequency_unit}This membership will be renewed automatically every %1 weeks.{/ts}</strong>
                  {elseif $frequency_unit eq 'month'}
                    <strong>{ts 1=$frequency_interval 2=$frequency_unit}This membership will be renewed automatically every %1 months.{/ts}</strong>
                  {elseif $frequency_unit eq 'year'}
                    <strong>{ts 1=$frequency_interval 2=$frequency_unit}This membership will be renewed automatically every %1 years.{/ts}</strong>
                  {/if}
                {else}
                  {* dev/translation#80 All 'every %1' strings are incorrectly using ts, but focusing on the most important one until we find a better fix. *}
                  {if $frequency_unit eq 'day'}
                    <strong>{ts}This membership will be renewed automatically every day.{/ts}</strong>
                  {elseif $frequency_unit eq 'week'}
                    <strong>{ts}This membership will be renewed automatically every week.{/ts}</strong>
                  {elseif $frequency_unit eq 'month'}
                    <strong>{ts}This membership will be renewed automatically every month.{/ts}</strong>
                  {elseif $frequency_unit eq 'year'}
                    <strong>{ts}This membership will be renewed automatically every year.{/ts}</strong>
                  {/if}
                {/if}
              <div class="description crm-auto-renew-cancel-info">{ts}Your initial membership fee will be processed once you complete the confirmation step. You will be able to cancel the auto-renewal option by visiting the web page link that will be included in your receipt.{/ts}</div>
            {/crmRegion}
          {else}
            {crmRegion name="contribution-confirm-recur"}
              {if $installments > 1}
                {if $frequency_interval > 1}
                  {if $frequency_unit eq 'day'}
                    <p><strong>{ts 1=$frequency_interval 2=$frequency_unit 3=$installments}This recurring contribution will be automatically processed every %1 days for a total %3 installments (including this initial contribution).{/ts}</strong></p>
                  {elseif $frequency_unit eq 'week'}
                    <p><strong>{ts 1=$frequency_interval 2=$frequency_unit 3=$installments}This recurring contribution will be automatically processed every %1 weeks for a total %3 installments (including this initial contribution).{/ts}</strong></p>
                  {elseif $frequency_unit eq 'month'}
                    <p><strong>{ts 1=$frequency_interval 2=$frequency_unit 3=$installments}This recurring contribution will be automatically processed every %1 months for a total %3 installments (including this initial contribution).{/ts}</strong></p>
                  {elseif $frequency_unit eq 'year'}
                    <p><strong>{ts 1=$frequency_interval 2=$frequency_unit 3=$installments}This recurring contribution will be automatically processed every %1 years for a total %3 installments (including this initial contribution).{/ts}</strong></p>
                  {/if}
                {else}
                  <p><strong>{ts 1=$frequency_unit 2=$installments}This recurring contribution will be automatically processed every %1 for a total %2 installments (including this initial contribution).{/ts}</strong></p>
                {/if}
              {else}
                {if $frequency_interval > 1}
                  {if $frequency_unit eq 'day'}
                    <p><strong>{ts 1=$frequency_interval}This recurring contribution will be automatically processed every %1 days.{/ts}</strong></p>
                  {elseif $frequency_unit eq 'week'}
                    <p><strong>{ts 1=$frequency_interval}This recurring contribution will be automatically processed every %1 weeks.{/ts}</strong></p>
                  {elseif $frequency_unit eq 'month'}
                    <p><strong>{ts 1=$frequency_interval}This recurring contribution will be automatically processed every %1 months.{/ts}</strong></p>
                  {elseif $frequency_unit eq 'year'}
                    <p><strong>{ts 1=$frequency_interval}This recurring contribution will be automatically processed every %1 years.{/ts}</strong></p>
                  {/if}
                {else}
                  {* dev/translation#32 All 'every %1' strings are incorrectly using ts, but focusing on the most important one until we find a better fix. *}
                  {if $frequency_unit eq 'day'}
                    <p><strong>{ts}This contribution will be automatically processed every day.{/ts}</strong></p>
                  {elseif $frequency_unit eq 'week'}
                    <p><strong>{ts}This contribution will be automatically processed every week.{/ts}</strong></p>
                  {elseif $frequency_unit eq 'month'}
                    <p><strong>{ts}This contribution will be automatically processed every month.{/ts}</strong></p>
                  {elseif $frequency_unit eq 'year'}
                    <p><strong>{ts}This contribution will be automatically processed every year.{/ts}</strong></p>
                  {/if}
                {/if}
              {/if}
            <p>{ts}Your initial contribution will be processed once you complete the confirmation step. You will be able to cancel the recurring contribution by visiting the web page link that will be included in your receipt.{/ts}</p>
            {/crmRegion}
          {/if}
        {/if}

        {if $is_pledge}
          {if $pledge_frequency_interval GT 1}
            <p>
              <strong>{ts 1=$pledge_frequency_interval 2=$pledge_frequency_unit 3=$pledge_installments}I pledge to contribute this amount every %1 %2s for %3 installments.{/ts}</strong>
            </p>
          {else}
            <p>
              <strong>{ts 1=$pledge_frequency_interval 2=$pledge_frequency_unit 3=$pledge_installments}I pledge to contribute this amount every %2 for %3 installments.{/ts}</strong>
            </p>
          {/if}
          {if $is_pay_later}
            <p>{ts 1=$receiptFromEmail 2=$button}Click &quot;%2&quot; below to register your pledge. You will be able to modify or cancel future pledge payments at any time by logging in to your account or contacting us at %1.{/ts}</p>
          {else}
            <p>{ts 1=$receiptFromEmail 2=$button}Your initial pledge payment will be processed when you click &quot;%2&quot; below. You will be able to modify or cancel future pledge payments at any time by logging in to your account or contacting us at %1.{/ts}</p>
          {/if}
        {/if}
      </div>
    </div>
  {/if}

  {if $onbehalfProfile && $onbehalfProfile|@count}
    <div class="crm-group onBehalf_display-group label-left crm-profile-view">
      {include file="CRM/UF/Form/Block.tpl" fields=$onbehalfProfile prefix='onbehalf' hideFieldset=false}
    </div>
  {/if}

  {if $honoreeProfileFields && $honoreeProfileFields|@count}
    <div class="crm-group honor_block-group">
      <div class="header-dark">
        {$soft_credit_type}
      </div>
      <div class="display-block">
        <div class="label-left crm-section honoree_profile-section">
          <strong>{$honorName}</strong><br/>
          {include file="CRM/UF/Form/Block.tpl" fields=$honoreeProfileFields mode=8 prefix='honor' hideFieldset=false}
        </div>
      </div>
    </div>
  {/if}

  {if $customPre}
    <fieldset class="label-left crm-profile-view">
      {include file="CRM/UF/Form/Block.tpl" fields=$customPre prefix=false hideFieldset=false}
    </fieldset>
  {/if}

  {if $pcpBlock && $pcp_display_in_roll}
    <div class="crm-group pcp_display-group">
      <div class="header-dark">
        {ts}Contribution Honor Roll{/ts}
      </div>
      <div class="display-block">
        {ts}List my contribution{/ts}
        {if $pcp_is_anonymous}
          <strong>{ts}anonymously{/ts}.</strong>
        {else}
          {ts}under the name{/ts}:
          <strong>{$pcp_roll_nickname}</strong>
          <br/>
          {if $pcp_personal_note}
            {ts}With the personal note{/ts}:
            <strong>{$pcp_personal_note}</strong>
          {else}
            <strong>{ts}With no personal note{/ts}</strong>
          {/if}
        {/if}
        <br/>
      </div>
    </div>
  {/if}

    {if $billingName or $address}
        <div class="crm-group billing_name_address-group">
          <div class="header-dark">
            {ts}Billing Name and Address{/ts}
          </div>
          <div class="crm-section no-label billing_name-section">
            <div class="content">{$billingName}</div>
            <div class="clear"></div>
          </div>
          <div class="crm-section no-label billing_address-section">
            <div class="content">{$address|nl2br}</div>
            <div class="clear"></div>
          </div>
        </div>
      {/if}
    {if $email && !$emailExists}
      <div class="crm-group contributor_email-group">
        <div class="header-dark">
          {ts}Your Email{/ts}
        </div>
        <div class="crm-section no-label contributor_email-section">
          <div class="content">{$email}</div>
          <div class="clear"></div>
        </div>
      </div>
    {/if}

  {* Show credit or debit card section for 'direct' mode, except for PayPal Express (detected because credit card number is empty) *}
    {crmRegion name="contribution-confirm-billing-block"}
    {if in_array('credit_card_number', $paymentFields) || in_array('bank_account_number', $paymentFields)}
      <div class="crm-group credit_card-group">
        {if $paymentFieldsetLabel}
          <div class="header-dark">
            {$paymentFieldsetLabel}
          </div>
        {/if}
        {if in_array('bank_account_number', $paymentFields) && $bank_account_number}
          <div class="display-block">
            {ts}Account Holder{/ts}: {$account_holder}<br/>
            {ts}Bank Account Number{/ts}: {$bank_account_number}<br/>
            {ts}Bank Identification Number{/ts}: {$bank_identification_number}<br/>
            {ts}Bank Name{/ts}: {$bank_name}<br/>
          </div>
          {if $paymentAgreementText}
            <div class="crm-group debit_agreement-group">
              <div class="header-dark">
                {$paymentAgreementTitle}
              </div>
              <div class="display-block">
                {$paymentAgreementText}
              </div>
            </div>
          {/if}
        {/if}
        {if in_array('credit_card_number', $paymentFields) && $credit_card_number}
          <div class="crm-section no-label credit_card_details-section">
            <div class="content">{$credit_card_type}</div>
            <div class="content">{$credit_card_number}</div>
            <div class="content">{if $credit_card_exp_date}{ts}Expires{/ts}: {$credit_card_exp_date|truncate:7:''|crmDate}{/if}</div>
            <div class="clear"></div>
          </div>
        {/if}
      </div>
    {/if}
    {/crmRegion}

  {include file="CRM/Contribute/Form/Contribution/PremiumBlock.tpl" context="confirmContribution" showPremiumSelectionFields=false preview=false}

  {if $customPost}
    <fieldset class="label-left crm-profile-view">
      {include file="CRM/UF/Form/Block.tpl" fields=$customPost prefix=false hideFieldset=false}
    </fieldset>
  {/if}

  {if $confirmText}
    <div class="messages status continue_instructions-section">
      <p>
        {$confirmText}
      </p>
    </div>
  {/if}

  <div id="crm-submit-buttons" class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
