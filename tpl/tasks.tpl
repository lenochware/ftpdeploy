<?elements
link lnpreview route "deploy/preview/task:{TASK}"
link lnhistory route "deploy/history/task:{TASK}/popup:1" lb "historie" popup
?>

<h2>{TITLE}</h2>
<table class="grid">
<tr><th>Aplikace</th><th></th></tr>
{block items}
  <tr class="link" onclick="{lnpreview.js}">
  	<td><i class="fa fa-cube" style="color:gray"></i> {TASK}</td>
  	<td align="right" onclick="event.cancelBubble = true;" width="100">{lnhistory}</td>
  </tr>
{/block}
</table>
