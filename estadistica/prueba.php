<?php
  // este sería el hash que tu darías o en tu caso $password_hash
  $hash = '12345';

if (password_verify('rasmuslerdorf', $hash)) {
    echo '¡La contraseña es válida!';
} else {
    echo 'La contraseña no es válida.';
}
?>