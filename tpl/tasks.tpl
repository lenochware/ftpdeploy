<?elements
class grid
string TITLE
string TASK
string DIR
link lnpreview route "deploy/preview/task:{TASK}"
link lnhistory route "deploy/history/task:{TASK}/popup:1" lb "historie" popup
pager pager
?>

<h2>{TITLE}</h2>
<table class="grid">
<tr><th>Aplikace</th><th></th></tr>
{block items}
	{if TASK}
  <tr class="link" onclick="{lnpreview.js}">
  	<td><i class="fa fa-cube" style="color:gray"></i> {TASK}</td>
  	<td align="right" onclick="event.cancelBubble = true;" width="100">{lnhistory}</td>
  </tr>
  {/if}
  {if DIR}
  <tr>
  	<td><i class="fa fa-folder" style="color:gray"></i> {DIR}</td>
  	<td align="right" width="100"></td>
  </tr>
  {/if}
{/block}
</table>