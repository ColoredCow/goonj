<h2>Glific Group Mapping</h2>

{if $success_message}
  <div class="messages status no-popup">
    <div class="icon inform-icon"></div>
    <div class="content">{$success_message}</div>
  </div>
{/if}

{if $error_message}
  <div class="messages error no-popup">
    <div class="icon alert-icon"></div>
    <div class="content">{$error_message}</div>
  </div>
{/if}

<form method="post" action="">
  <table class="form-layout-compressed">
    <tr>
      <td><label for="civicrm_group_id">CiviCRM Group</label></td>
      <td>
        <select name="civicrm_group_id" id="civicrm_group_id" class="select2" required>
          <option value="">-- Select CiviCRM Group --</option>
          {foreach from=$civicrmGroups key=group_id item=group_title}
            <option value="{$group_id}">{$group_title|escape}</option>
          {/foreach}
        </select>
      </td>
    </tr>

    <tr>
      <td><label for="glific_group_id">Glific Group</label></td>
      <td>
        <select name="glific_group_id" id="glific_group_id" class="select2" required>
          <option value="">-- Select Glific Group --</option>
          {foreach from=$groups item=glificGroup}
            <option value="{$glificGroup.id}">{$glificGroup.label|escape}</option>
          {/foreach}
        </select>
      </td>
    </tr>

    <tr>
      <td colspan="2">
        <input type="submit" name="add_rule" value="Add Rule" class="btn btn-primary" />
      </td>
    </tr>
  </table>
</form>

<hr>

<h3>Existing Group Mappings</h3>

{if $mappings|@count > 0}
  <table class="selector">
    <thead>
      <tr>
        <th>CiviCRM Group</th>
        <th>CiviCRM Group ID</th>
        <th>Glific Group ID</th>
        <th>Glific Group Name</th>
        <th>Last Sync Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$mappings item=map}
        <tr>
          <td>{$map.group_name|escape}</td>
          <td>{$map.collection_id}</td>
          <td>{$map.group_id}</td>
          <td>{$map.glific_group_name|escape}</td>
          <td>{$map.last_sync_date|default:'N/A'}</td>
          <td>
            <form method="post" action="" style="display:inline;">
              <input type="hidden" name="delete_mapping_id" value="{$map.id}">
              <input type="submit" name="delete_rule" value="Delete" class="crm-button crm-button-delete" onclick="return confirm('Are you sure you want to delete this mapping?');">
            </form>
          </td>
        </tr>
      {/foreach}
    </tbody>
  </table>
{else}
  <p>No mappings found yet.</p>
{/if}
