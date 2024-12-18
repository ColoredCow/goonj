
{*block for linked condition *}
<h3>{ts}Linked Action(s){/ts}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block">
  <div class="crm-section">
    <div id="civirule_wrapper" class="dataTables_wrapper">
      <table id="civirule-table" class="display">
        <thead>
          <tr>
            <th>{ts}Name{/ts}</th>
            <th>{ts}Extra parameters{/ts}</th>
            <th class="nosort">&nbsp;</th>
            <th id="nosort">&nbsp;</th>
          </tr>
        </thead>
        <tbody>
          {assign var="rowClass" value="odd_row"}
          {assign var="rowNumber" value=1}
          {foreach from=$ruleActions key=action_id item=ruleAction}
            <tr id="row_{$rowNumber}" class={$rowClass}>
              <td hidden="1" id="ruleActionId">{$ruleAction.id}</td>
              <td>{$ruleAction.label}</td>
              {if !empty($ruleAction.formattedConditionParams)}
                <td>{$ruleAction.formattedConditionParams}</td>
              {else}
                <td>&nbsp;</td>
              {/if}
              <td>
                  {$ruleAction.formattedDelay}
              </td>
              <td>
                <span>
                  {foreach from=$ruleAction.actions item=actionLink}
                    {$actionLink}
                  {/foreach}
                </span>
              </td>
            </tr>
            {if $rowClass eq "odd_row"}
              {assign var="rowClass" value="even-row"}
            {else}
              {assign var="rowClass" value="odd-row"}
            {/if}
            {assign var="rowNumber" value=$rowNumber+1}
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
  <div class="crm-submit-buttons">
    <a class="add button" title="Add Action" href="{$ruleActionAddUrl}">
      <span><i class="crm-i fa-plus-circle"></i> {ts}Add Action{/ts}</span></a>
  </div>
</div>

