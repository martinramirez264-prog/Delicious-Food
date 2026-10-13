<?php
// Usa el mismo includes/session.php que session.php y pedidos.php, así la sesión
// que se destruye es exactamente la que creó el login.
require_once __DIR__.'/../includes/session.php';

$_SESSION=[];

if (ini_get('session.use_cookies')) {
 $p=session_get_cookie_params();
 setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}

if (session_status()===PHP_SESSION_ACTIVE) session_destroy();

responder(['ok'=>true]);
