<?php
require_once __DIR__.'/../includes/session.php';
if (empty($_SESSION['usuario'])) responder(['ok'=>true,'logueado'=>false]);
responder(['ok'=>true,'logueado'=>true,'usuario'=>$_SESSION['usuario']]);
