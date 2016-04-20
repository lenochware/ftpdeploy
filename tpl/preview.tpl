<?elements
class gridform name "preview" route "deploy/task:{GET.task}"
env GET
check SEL default "1"
input COMMENT size "100" required html_required
input PASSWORD password required html_required
bind STATUS_TEXT list "modified,změna,deleted,smazáno,created,vytvořeno,unwatch,dál nesledovat" field "STATUS"
button commit lb " Publikovat" tag "button" glyph "fa fa-flag"
?>
<style>
	.created { color: blue;}
	.deleted { color: red;}
	.unwatch { color: gray;}
	.selected { background-color: #f3fff0; }

	.no-text-select {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
	}
</style>
<h2>{GET.task}: Přehled změn</h2>
<table class="grid">
<tr>
	<th width="30"></th>
	<th>Soubor</th>
	<th>Stav</th>
	<th></th>
</tr>
{block items}
  <tr class="selected link no-text-select">
  	<td><input type="checkbox" name="rowdata[][FILE]" checked="checked" value="{FILENAME} {STATUS}" onclick="this.checked = !this.checked;"></td>
  	<td>{FILENAME}</td>
  	<td><span class="{STATUS}">{STATUS_TEXT}</span></td>
  	<td align="right"></td>
  </tr>
{block else}
  <tr>
  	<td colspan="4">Nenalezeny žádné změny.</td>
  </tr>
{/block}
</table><br>
Celkem {TOTAL} souborů.

<br>
<table class="form">
<tr>
	<td>Server:</td>
	<td><i class="fa fa-desktop"></i> {HOST}</td>
</tr>
<tr>
	<td>Adresář:</td>
	<td>{REMOTEDIR}</td>
</tr>
<tr>
	<td>Komentář:</td>
	<td>{COMMENT}</td>
</tr>
<tr>
	<td>Heslo:</td>
	<td>{PASSWORD}</td>
</tr>
<tr>
	<td>{commit}</td>
	<td></td>
</tr>
</table>	
<script language="JavaScript">

var selected = false;
var selecting = false;

function toggleSelected()
{
	selected = !$(this).hasClass('selected');
	setSelected(this);
}

function setSelected(tr)
{
	var checkbox = $(tr).find(':checkbox').get(0);
	checkbox.checked = selected;
	$(tr).toggleClass('selected', selected);
}

function init()
{
	$("table.grid tr")
		//.click(toggleSelected)
		.mouseup(function() { selecting = false; })
		.mousedown(function() { 
			selecting = true; 
			selected = !$(this).hasClass('selected');
			setSelected(this);
		})
		.mousemove(function() { if (selecting) setSelected(this); } );
}
$(document).ready(init);
</script>