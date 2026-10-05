<?php require __DIR__.'/app/bootstrap.php'; $u=user(); go($u?$u['role'].'/':'login.php');
