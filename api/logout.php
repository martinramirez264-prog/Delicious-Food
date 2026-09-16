<?php
require_once __DIR__.'/../includes/session.php';
$_SESSION=[];
session_destroy();
responder(['ok'=>true,'mensaje'=>'Sesión cerrada.']);
