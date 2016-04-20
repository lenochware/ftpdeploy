<?php

//vrati aktualni datum v mysql formatu.
function now() {
  return date("Y-m-d H:i:s");
}

function sanitize($s, $type) {
  $pattern = array(
  	'alphanum' => '/[a-zA-Z0-9]+/',
  );
  if (!$pattern[$type]) throw new Exception("Unknown type '$type'");
  $s= preg_match($pattern[$type], $s, $matches);
  return $matches[0];
}

?>
