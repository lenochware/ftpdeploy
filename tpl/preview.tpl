<?elements
class gridform name "preview" route "deploy/task:{GET.task}"
env GET
primary ID
check SEL default "1"
input COMMENT size "100"
input PASSWORD password
string HOST
string FTPS
string SFTP
string TOTAL
string REMOTEDIR
string FILENAME
string STATUS
bind STATUS_TEXT list "modified,změna,deleted,smazáno,created,vytvořeno,unwatch,dál nesledovat" field "STATUS"
button commit lb " Publikovat" tag "button" glyph "fa fa-flag"
button skip lb "Přeskočit..." tag "button" confirm "Označit jako nahrané?"
link history route "deploy/history/task:{TASK}/popup:1" lb "Historie" popup
pager pager pglen "10000"
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
<table id="file-list" class="grid">
<tr class="hdr">
	<th width="30"><input type="checkbox" id="check-all" checked="checked" value="1"></th>
	<th>Soubor</th>
	<th>Stav</th>
	<th></th>
</tr>
{block items}
  <tr class="selected link no-text-select">
  	<td><input type="checkbox" name="rowdata[][FILE]" checked="checked" class="send-file" value="{FILENAME}*{STATUS}"></td>
	  <td class="filename" onclick="template_form_load_diff('{FILENAME}', '{GET.task}')">{FILENAME}</td>
  	<td><span class="{STATUS}">{STATUS_TEXT}</span></td>
  	<td align="right"></td>
  </tr>
{block else}
  <tr>
  	<td colspan="4">Nenalezeny žádné změny.</td>
  </tr>
{/block}
</table>
<br>

Celkem {TOTAL} souborů.

<br>
<table class="form">
<tr>
	<td>{if COLOR}<i class="fa fa-circle" style="color:{COLOR}"></i> {/if}Server:</td>
	<td><i class="fa fa-desktop"></i> {if FTPS}<b style="color:green">ftps</b> {/if}{if SFTP}<b style="color:darkgreen">sftp</b> {/if}{HOST}</td>
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
	<td colspan="2">{commit} {skip} &nbsp; {history}</td>
	<td></td>
</tr>
</table>
<script language="JavaScript">

function init()
{
	$("#check-all").change(function() {
    $(".send-file").prop('checked', $(this).prop('checked'));
    $("#file-list tr").toggleClass("selected", $(this).prop('checked'));
	});

  $(".send-file").change(function() {
      $(this).closest('tr').toggleClass("selected", $(this).prop('checked'));
  });

  $('body').on('keydown', scrollDiv);
  
}

$(document).ready(init);
</script>