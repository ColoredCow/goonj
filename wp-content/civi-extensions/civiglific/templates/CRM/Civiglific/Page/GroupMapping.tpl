<div class="crm-block crm-content-block">

  {if isset($error)}
    <div class="error">{$error}</div>
  {/if}

<div class="crm-block crm-content-block">
  <h3>Glific Groups</h3>

  {if $groups|@count > 0}
    <ul>
      {foreach from=$groups item=group}
        <li>{$group.label} (ID: {$group.id})</li>
      {/foreach}
    </ul>
  {else}
    <p>No groups found.</p>
  {/if}
</div>

</div>
