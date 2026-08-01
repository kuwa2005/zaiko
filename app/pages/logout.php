<?php
declare(strict_types=1);
$_SESSION['担当者'] = null;
unset($_SESSION['担当者']);
redirect(url('login'));
