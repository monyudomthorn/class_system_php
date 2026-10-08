<?php
    session_start();
    $_SESSION['email'] = '';
    $_SESSION['password'] = '';
    header('Location: login.php');
?>