<?elements
head HEAD scripts "css/site.css,css/menu.css,js/jquery.js,vendor/lenochware/pclib/pclib/www/pclib.js,js/global.js"
messages PRECONTENT noescape
string CONTENT noescape
string TITLE
string MENU noescape
string VERSION
navig NAVIG
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{if TITLE}{TITLE} | {/if}ftp-deploy</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
  {HEAD}
</head>
<body>
<div id="site-top">
<h1><span class="fa fa-cloud-upload"></span> ftp-deploy</h1>
<div style="position: absolute; top: 80px; left: 10px;">{NAVIG}</div>
<div style="position: absolute; top: 108px; right: 10px;">{VERSION}</div>
</div>
<div id="menu">{MENU}</div>

<div id="site-content">
{PRECONTENT}{CONTENT}
</div>
<div class="site-footer"></div>
<script>
	$(document).ready(init_global);
</script>
</body>
</html>