<?php
// Odeslání HTTP headeru, pokud ještě nebyl odeslán výstup
if ( !headers_sent() ) {
    header('Content-Type: text/html; charset=UTF-8');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
</head>
<body>
</body>
</html>
