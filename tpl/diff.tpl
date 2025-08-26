<?elements
string BODY noescape
?>
<style>
.keyboard-hints {
  font-size: 12px;
  font-family: sans-serif;
  display: flex;
  gap: 1em;
  align-items: center;
}

.keyboard-hints .key {
  display: inline-block;
  padding: 2px;
  border: 1px solid #ccc;
  border-radius: 6px;
  background-color: #f8f8f8;
  font-weight: normal;
  font-family: monospace;
  min-width: 1.5em;
  text-align: center;
}	
</style>
<div style="padding-left:1em;position: sticky;top: 0;background-color:white;width:auto">
<h2>{TITLE}</h2>
<div class="keyboard-hints">
  <span class="key">↑</span> Prev
  <span class="key">↓</span> Next
  <span class="key">S</span> Show only changes
</div>
</div><hr>



{BODY}