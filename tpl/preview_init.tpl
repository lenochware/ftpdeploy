<?elements
class grid name "preview_init"
pager pager pglen "10000"
?>
<form action="?r=deploy/init&task={TASK}" method="post">
<h1>Inicializace</h1>
<table class="form">
	<tr>
		<td>Nalezeno celkem {TOTAL} souborů.</td>
	</tr>
	<tr>
		<td>Seznam vybraných souborů můžete omezit pomocí direktiv <b>include, exclude</b> v konfiguračním souboru.<br>
		</td>
	</tr>
	<tr>
		<td>
		<input type="submit" name="save" value="Potvrdit a nepublikovat">
		<input type="submit" name="no_save" value="Chci publikovat..."></td>
	</tr>
</table>
</form>

<h2>{TASK}: Přehled souborů</h2>

<table class="grid strips">
<tr>
	<th>Soubor</th>
</tr>
{block items}
  <tr>
  	<td>{FILENAME}</td>
  </tr>
{block else}
  <tr>
  	<td colspan="4">Nenalezeny žádné soubory.</td>
  </tr>
{/block}
</table>
