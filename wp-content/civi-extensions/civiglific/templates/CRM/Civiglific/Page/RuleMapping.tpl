<h3>Add New Rule</h3>
<form method="post" action="" class="form-inline" style="margin-top: 20px;">
  <div style="margin-bottom: 10px;">
    <label for="civicrm_group_id">CiviCRM Group:</label>
    <select name="civicrm_group_id" id="civicrm_group_id">
      {foreach from=$civicrmGroups item=group}
        <option value="{$group.id}">{$group.title}</option>
      {/foreach}
    </select>
  </div>

  <div style="margin-bottom: 10px;">
    <label for="glific_group_id">Glific Group:</label>
    <select name="glific_group_id" id="glific_group_id">
      {foreach from=$groups item=group}
        <option value="{$group.id}">{$group.label}</option>
      {/foreach}
    </select>
  </div>

  <input type="submit" name="add_rule" value="Add Rule" class="btn btn-primary" />
</form>
