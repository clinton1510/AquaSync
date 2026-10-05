<?php
// One-time, non-destructive migration for the pre-existing local schema.
if(PHP_SAPI!=='cli')exit;
$db=new PDO('mysql:host=127.0.0.1;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tables=$db->query('SHOW TABLES FROM aquasync_db')->fetchAll(PDO::FETCH_COLUMN);
if(!$tables || in_array('subscription_date',$db->query('SHOW COLUMNS FROM aquasync_db.orders')->fetchAll(PDO::FETCH_COLUMN)))exit("Archive not needed.\n");
$archive='aquasync_archive_'.date('Ymd_His');
$db->exec('CREATE DATABASE `'.$archive.'` CHARACTER SET utf8mb4');
$moves=array_map(fn($table)=>'`aquasync_db`.`'.str_replace('`','``',$table).'` TO `'.$archive.'`.`'.str_replace('`','``',$table).'`',$tables);
$db->exec('RENAME TABLE '.implode(', ',$moves));
echo "Preserved existing tables and data in $archive\n";
