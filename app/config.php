<?php
return ['host'=>getenv('DB_HOST') ?: '127.0.0.1','port'=>getenv('DB_PORT') ?: '3306','database'=>getenv('DB_NAME') ?: 'aquasync_db','username'=>getenv('DB_USER') ?: 'root','password'=>getenv('DB_PASSWORD') ?: '', 'base'=>getenv('APP_BASE') ?: '/AquaSync'];
